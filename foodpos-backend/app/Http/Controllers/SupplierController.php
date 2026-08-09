<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportSuppliersRequest;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\PartyBalanceAdjustment;
use App\Models\Supplier;
use App\Services\PartyBalanceAdjustmentService;
use App\Services\SupplierImportService;
use App\Support\CatalogListingQuery;
use App\Support\SupplierExport;
use App\Support\SupplierImportSampleExport;
use App\Support\ListingPerPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierController extends Controller
{
    public function __construct(
        private SupplierImportService $supplierImporter,
        private PartyBalanceAdjustmentService $balanceAdjustmentService,
    ) {}

    /**
     * Display a listing of suppliers.
     */
    public function index(Request $request)
    {
        $search = CatalogListingQuery::searchFromRequest($request);
        $perPage = ListingPerPage::fromRequest($request);
        $like = $search !== '' ? '%'.CatalogListingQuery::escapeLike($search).'%' : null;

        $query = Supplier::with('company')->latest();

        if ($like) {
            $query->where(function ($q) use ($like, $search) {
                $q->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('contact_person', 'like', $like)
                    ->orWhere('tax_id', 'like', $like);

                $digits = preg_replace('/\D/', '', $search);
                if ($digits !== '') {
                    $q->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') LIKE ?", ['%'.$digits.'%']);
                }
            });
        }

        $suppliers = $query->paginate($perPage)->withQueryString();

        return view('suppliers.index', compact('suppliers', 'search', 'perPage'));
    }

    /**
     * Show the form for creating a new supplier.
     */
    public function create()
    {
        $user = Auth::user();
        $suggestedCode = Supplier::generateNextCode($user->company_id);

        return view('suppliers.create', compact('suggestedCode'));
    }

    /**
     * Store a newly created supplier.
     */
    public function store(StoreSupplierRequest $request)
    {
        $user = Auth::user();

        $supplier = Supplier::create([
            'company_id' => $user->company_id,
            'name' => $request->name,
            'code' => Supplier::resolveCode($user->company_id, $request->input('code')),
            'contact_person' => $request->contact_person,
            'email' => $request->email,
            'phone' => $request->phone,
            'whatsapp' => $request->whatsapp,
            'address' => $request->address,
            'tax_id' => $request->tax_id,
            'status' => $request->status ?? 'active',
            'balance' => $request->balance ?? 0,
            'notes' => $request->notes,
        ]);

        $openingBalance = round((float) ($supplier->balance ?? 0), 2);
        if (abs($openingBalance) >= 0.01) {
            PartyBalanceAdjustment::create([
                'company_id' => $supplier->company_id,
                'party_type' => PartyBalanceAdjustment::PARTY_SUPPLIER,
                'party_id' => $supplier->id,
                'previous_balance' => 0,
                'new_balance' => $openingBalance,
                'reason' => 'Opening balance',
                'created_by' => $user->id,
            ]);
        }

        return redirect()
            ->route('suppliers.index')
            ->with('success', "Supplier '{$supplier->name}' created successfully.");
    }

    /**
     * Display the specified supplier.
     */
    public function show(Supplier $supplier)
    {
        $supplier->load('company');
        $balanceAdjustments = PartyBalanceAdjustment::query()
            ->where('company_id', $supplier->company_id)
            ->where('party_type', PartyBalanceAdjustment::PARTY_SUPPLIER)
            ->where('party_id', $supplier->id)
            ->with('creator')
            ->latest()
            ->limit(10)
            ->get();

        return view('suppliers.show', compact('supplier', 'balanceAdjustments'));
    }

    /**
     * Show the form for editing the specified supplier.
     */
    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', compact('supplier'));
    }

    /**
     * Update the specified supplier.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        $code = trim((string) $request->input('code', ''));
        if ($code === '') {
            $code = $supplier->code ?: Supplier::generateNextCode($supplier->company_id);
        }

        $supplier->update([
            'name' => $request->name,
            'code' => $code,
            'contact_person' => $request->contact_person,
            'email' => $request->email,
            'phone' => $request->phone,
            'whatsapp' => $request->whatsapp,
            'address' => $request->address,
            'tax_id' => $request->tax_id,
            'status' => $request->status ?? 'active',
            'notes' => $request->notes,
        ]);

        return redirect()
            ->route('suppliers.index')
            ->with('success', "Supplier '{$supplier->name}' updated successfully.");
    }

    /**
     * Remove the specified supplier.
     */
    public function destroy(Supplier $supplier)
    {
        $name = $supplier->name;
        $supplier->delete();

        return redirect()
            ->route('suppliers.index')
            ->with('success', "Supplier '{$name}' deleted successfully.");
    }

    /**
     * Show bulk import form.
     */
    public function import(): View
    {
        return view('suppliers.import', [
            'expectedHeaders' => SupplierImportService::expectedHeaders(),
            'importResult' => session('importResult'),
        ]);
    }

    /**
     * Download sample import file.
     */
    public function importSample(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');

        return (new SupplierImportSampleExport)->download($format);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');

        return (new SupplierExport)->download($format);
    }

    /**
     * Process bulk import upload.
     */
    public function importStore(ImportSuppliersRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $result = $this->supplierImporter->import($request->file('file'), (int) $user->company_id);

        $message = sprintf(
            'Import finished: %d created, %d updated.',
            $result['created'],
            $result['updated'],
        );

        if ($result['skipped'] > 0) {
            $message .= sprintf(' %d row(s) skipped.', $result['skipped']);
        }

        return redirect()
            ->route('suppliers.import')
            ->with('importResult', $result)
            ->with($result['created'] + $result['updated'] > 0 ? 'success' : 'error', $message);
    }

    public function balanceAdjustment(Supplier $supplier): View
    {
        abort_unless(Auth::user()->hasAppPermission('suppliers.adjust-balance'), 403);

        return view('suppliers.balance-adjustment', compact('supplier'));
    }

    public function storeBalanceAdjustment(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless(Auth::user()->hasAppPermission('suppliers.adjust-balance'), 403);

        $validated = $request->validate([
            'new_balance' => ['required', 'numeric', 'min:-99999999.99', 'max:99999999.99'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->balanceAdjustmentService->adjustSupplier(
                $supplier,
                (float) $validated['new_balance'],
                Auth::user(),
                $validated['reason'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['new_balance' => $e->getMessage()]);
        }

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', 'Supplier balance updated successfully.');
    }
}
