<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportCustomersRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\PartyBalanceAdjustment;
use App\Services\CustomerImportService;
use App\Services\PartyBalanceAdjustmentService;
use App\Support\CatalogListingQuery;
use App\Support\CustomerExport;
use App\Support\CustomerImportSampleExport;
use App\Support\ListingPerPage;
use App\Support\PartyBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerImportService $customerImporter,
        private PartyBalanceAdjustmentService $balanceAdjustmentService,
    ) {}

    /**
     * Display a listing of customers.
     */
    public function index(Request $request)
    {
        $search = CatalogListingQuery::searchFromRequest($request);
        $perPage = ListingPerPage::fromRequest($request);
        $like = $search !== '' ? '%'.CatalogListingQuery::escapeLike($search).'%' : null;

        $query = Customer::with(['addresses'])->latest();

        if ($like) {
            $query->where(function ($q) use ($like, $search) {
                $q->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('notes', 'like', $like);

                $digits = preg_replace('/\D/', '', $search);
                if ($digits !== '') {
                    $q->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') LIKE ?", ['%'.$digits.'%']);
                }
            });
        }

        $customers = $query->paginate($perPage)->withQueryString();

        return view('customers.index', compact('customers', 'search', 'perPage'));
    }

    /**
     * Show the form for creating a new customer.
     */
    public function create()
    {
        $user = Auth::user();
        $suggestedCode = Customer::generateNextCode($user->company_id);

        return view('customers.create', compact('suggestedCode'));
    }

    /**
     * Store a newly created customer.
     */
    public function store(StoreCustomerRequest $request)
    {
        $user = Auth::user();

        try {
            $customer = Customer::create([
                'company_id' => $user->company_id,
                'name' => $request->name,
                'code' => Customer::resolveCode($user->company_id, $request->input('code')),
                'email' => $request->email,
                'phone' => $request->phone,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'notes' => $request->notes,
                'balance' => $request->balance ?? 0,
                'is_active' => $request->has('is_active') ? true : false,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'customers_company_id_phone_unique')) {
                $conflict = Customer::findPhoneConflict($user->company_id, $request->phone);
                $message = Customer::phoneDuplicateMessage($conflict);

                return back()
                    ->withInput()
                    ->withErrors(['phone' => $message])
                    ->with('error', $message);
            }

            throw $e;
        }

        $openingBalance = round((float) ($customer->balance ?? 0), 2);
        if (abs($openingBalance) >= 0.01) {
            PartyBalanceAdjustment::create([
                'company_id' => $customer->company_id,
                'party_type' => PartyBalanceAdjustment::PARTY_CUSTOMER,
                'party_id' => $customer->id,
                'previous_balance' => 0,
                'new_balance' => $openingBalance,
                'reason' => 'Opening balance',
                'created_by' => $user->id,
            ]);
        }

        // Create addresses if provided
        if ($request->has('addresses') && is_array($request->addresses)) {
            foreach ($request->addresses as $addressData) {
                CustomerAddress::create([
                    'customer_id' => $customer->id,
                    'type' => $addressData['type'] ?? null,
                    'label' => $addressData['label'],
                    'contact_name' => $addressData['contact_name'] ?? null,
                    'contact_phone' => $addressData['contact_phone'] ?? null,
                    'address_line_1' => $addressData['address_line_1'],
                    'address_line_2' => $addressData['address_line_2'] ?? null,
                    'city' => $addressData['city'],
                    'state' => $addressData['state'] ?? null,
                    'postal_code' => $addressData['postal_code'] ?? null,
                    'country' => $addressData['country'] ?? null,
                    'latitude' => $addressData['latitude'] ?? null,
                    'longitude' => $addressData['longitude'] ?? null,
                    'is_default' => isset($addressData['is_default']) && $addressData['is_default'],
                    'delivery_instructions' => $addressData['delivery_instructions'] ?? null,
                ]);
            }
        }

        return redirect()->route('customers.index')
            ->with('success', "Customer '{$customer->name}' created successfully.");
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer)
    {
        $customer->load([
            'addresses',
            'company',
            'payments' => fn ($q) => $q->with(['branch', 'moneySource', 'creator'])->latest()->limit(10),
        ]);

        $balanceAdjustments = PartyBalanceAdjustment::query()
            ->where('company_id', $customer->company_id)
            ->where('party_type', PartyBalanceAdjustment::PARTY_CUSTOMER)
            ->where('party_id', $customer->id)
            ->with('creator')
            ->latest()
            ->limit(10)
            ->get();

        return view('customers.show', compact('customer', 'balanceAdjustments'));
    }

    /**
     * Show the form for editing the specified customer.
     */
    public function edit(Customer $customer)
    {
        if (! $customer->relationLoaded('addresses')) {
            $customer->load(['addresses']);
        }

        return view('customers.edit', compact('customer'));
    }

    /**
     * Update the specified customer.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $code = trim((string) $request->input('code', ''));
        if ($code === '') {
            $code = $customer->code ?: Customer::generateNextCode($customer->company_id);
        }

        $customer->update([
            'name' => $request->name,
            'code' => $code,
            'email' => $request->email,
            'phone' => $request->phone,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'notes' => $request->notes,
            'is_active' => $request->has('is_active') ? true : false,
        ]);

        // Handle addresses
        if ($request->has('addresses') && is_array($request->addresses)) {
            $existingAddressIds = [];

            foreach ($request->addresses as $addressData) {
                if (isset($addressData['id'])) {
                    $address = CustomerAddress::where('customer_id', $customer->id)
                        ->find($addressData['id']);

                    if ($address) {
                        $address->update([
                            'type' => $addressData['type'] ?? null,
                            'label' => $addressData['label'],
                            'contact_name' => $addressData['contact_name'] ?? null,
                            'contact_phone' => $addressData['contact_phone'] ?? null,
                            'address_line_1' => $addressData['address_line_1'],
                            'address_line_2' => $addressData['address_line_2'] ?? null,
                            'city' => $addressData['city'],
                            'state' => $addressData['state'] ?? null,
                            'postal_code' => $addressData['postal_code'] ?? null,
                            'country' => $addressData['country'] ?? null,
                            'latitude' => $addressData['latitude'] ?? null,
                            'longitude' => $addressData['longitude'] ?? null,
                            'is_default' => isset($addressData['is_default']) && $addressData['is_default'],
                            'delivery_instructions' => $addressData['delivery_instructions'] ?? null,
                        ]);
                        $existingAddressIds[] = $address->id;
                    }
                } else {
                    $newAddress = CustomerAddress::create([
                        'customer_id' => $customer->id,
                        'type' => $addressData['type'] ?? null,
                        'label' => $addressData['label'],
                        'contact_name' => $addressData['contact_name'] ?? null,
                        'contact_phone' => $addressData['contact_phone'] ?? null,
                        'address_line_1' => $addressData['address_line_1'],
                        'address_line_2' => $addressData['address_line_2'] ?? null,
                        'city' => $addressData['city'],
                        'state' => $addressData['state'] ?? null,
                        'postal_code' => $addressData['postal_code'] ?? null,
                        'country' => $addressData['country'] ?? null,
                        'latitude' => $addressData['latitude'] ?? null,
                        'longitude' => $addressData['longitude'] ?? null,
                        'is_default' => isset($addressData['is_default']) && $addressData['is_default'],
                        'delivery_instructions' => $addressData['delivery_instructions'] ?? null,
                    ]);
                    $existingAddressIds[] = $newAddress->id;
                }
            }

            CustomerAddress::where('customer_id', $customer->id)
                ->whereNotIn('id', $existingAddressIds)
                ->delete();
        }

        return redirect()->route('customers.index')
            ->with('success', "Customer '{$customer->name}' updated successfully.");
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(Customer $customer)
    {
        if ($customer->is_default) {
            return redirect()->route('customers.index')
                ->withErrors(['error' => 'Default customers cannot be deleted.']);
        }

        $customerName = $customer->name;
        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', "Customer '{$customerName}' deleted successfully.");
    }

    /**
     * Show bulk import form.
     */
    public function import(): View
    {
        return view('customers.import', [
            'expectedHeaders' => CustomerImportService::expectedHeaders(),
            'importResult' => session('importResult'),
        ]);
    }

    /**
     * Download sample import file.
     */
    public function importSample(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');

        return (new CustomerImportSampleExport)->download($format);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');

        return (new CustomerExport)->download($format);
    }

    /**
     * Process bulk import upload.
     */
    public function importStore(ImportCustomersRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $result = $this->customerImporter->import($request->file('file'), (int) $user->company_id);

        $message = sprintf(
            'Import finished: %d created, %d updated.',
            $result['created'],
            $result['updated'],
        );

        if ($result['skipped'] > 0) {
            $message .= sprintf(' %d row(s) skipped.', $result['skipped']);
        }

        return redirect()
            ->route('customers.import')
            ->with('importResult', $result)
            ->with($result['created'] + $result['updated'] > 0 ? 'success' : 'error', $message);
    }

    /**
     * Quick create customer from POS (name, phone, address only). Returns JSON.
     */
    public function quickStore(Request $request)
    {
        $user = Auth::user();
        $companyId = Customer::requireTenantCompanyId((int) $user->company_id);
        $phone = Customer::normalizePhone($request->input('phone'));

        $request->merge(['phone' => $phone]);

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => Customer::tenantPhoneValidationRules($companyId),
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:5000',
        ], [
            'phone.unique' => 'This phone number is already assigned to another customer in your company.',
        ]);

        try {
            $customer = Customer::create([
                'company_id' => $companyId,
                'name' => $request->name,
                'code' => Customer::resolveCode($companyId, null),
                'phone' => $phone,
                'email' => $request->filled('email') ? $request->email : null,
                'is_active' => true,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'customers_company_id_phone_unique')) {
                return response()->json([
                    'message' => 'This phone number is already assigned to another customer.',
                    'errors' => ['phone' => ['This phone number is already assigned to another customer.']],
                ], 422);
            }

            throw $e;
        }

        $addressLine = trim((string) $request->input('address', ''));
        if ($addressLine !== '') {
            CustomerAddress::create([
                'customer_id' => $customer->id,
                'label' => 'Default',
                'address_line_1' => $addressLine,
                'city' => '-',
                'is_default' => true,
            ]);
        }

        return response()->json([
            'id' => $customer->id,
            'code' => $customer->code ?? '',
            'name' => $customer->name,
            'phone' => $customer->phone ?? '',
            'email' => $customer->email ?? '',
            'address' => $addressLine,
        ]);
    }

    /**
     * Search customers for POS by name, code, mobile, and/or email.
     */
    public function search(Request $request)
    {
        $raw = trim((string) $request->get('q', $request->get('phone', '')));
        $limit = min(100, max(1, (int) $request->get('limit', 100)));

        $query = Customer::query()
            ->where('is_active', true)
            ->with('addresses');

        if ($raw !== '') {
            $digits = preg_replace('/\D/', '', $raw);

            $query->where(function ($q) use ($raw, $digits) {
                $q->where('name', 'like', '%'.$raw.'%')
                    ->orWhere('code', 'like', '%'.$raw.'%')
                    ->orWhere('email', 'like', '%'.$raw.'%');
                if ($digits !== '') {
                    $q->orWhere('phone', 'like', '%'.$raw.'%')
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') LIKE ?", ['%'.$digits.'%']);
                }
            });
        }

        $customers = $query
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json($customers->map(fn (Customer $c) => $this->customerSearchPayload($c))->values());
    }

    /**
     * @return array<string, mixed>
     */
    private function customerSearchPayload(Customer $c): array
    {
        $addresses = $c->addresses->map(function ($addr) {
            return [
                'id' => $addr->id,
                'label' => $addr->label ?? 'Address',
                'full_address' => $addr->full_address,
                'address_line_1' => $addr->address_line_1,
                'is_default' => (bool) $addr->is_default,
            ];
        })->values()->toArray();

        $primaryAddress = collect($addresses)->firstWhere('is_default', true) ?? ($addresses[0] ?? null);

        return [
            'id' => $c->id,
            'code' => $c->code ?? '',
            'name' => $c->name,
            'display_label' => $c->displayLabel(),
            'phone' => $c->phone ?? '',
            'email' => $c->email ?? '',
            'balance' => round((float) ($c->balance ?? 0), 2),
            'credit_available' => PartyBalance::customerCreditAvailable((float) ($c->balance ?? 0)),
            'primary_address' => $primaryAddress
                ? ($primaryAddress['full_address'] ?? $primaryAddress['address_line_1'] ?? '')
                : '',
            'addresses' => $addresses,
        ];
    }

    public function balanceAdjustment(Customer $customer): View
    {
        abort_unless(Auth::user()->hasAppPermission('customers.adjust-balance'), 403);

        return view('customers.balance-adjustment', compact('customer'));
    }

    public function storeBalanceAdjustment(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless(Auth::user()->hasAppPermission('customers.adjust-balance'), 403);

        $validated = $request->validate([
            'new_balance' => ['required', 'numeric', 'min:-99999999.99', 'max:99999999.99'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->balanceAdjustmentService->adjustCustomer(
                $customer,
                (float) $validated['new_balance'],
                Auth::user(),
                $validated['reason'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['new_balance' => $e->getMessage()]);
        }

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Customer balance updated successfully.');
    }
}
