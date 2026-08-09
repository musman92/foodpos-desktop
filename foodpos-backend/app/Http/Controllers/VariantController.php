<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportVariantsRequest;
use App\Http\Requests\StoreVariantRequest;
use App\Http\Requests\UpdateVariantRequest;
use App\Models\Variant;
use App\Services\VariantImportService;
use App\Support\VariantExport;
use App\Support\VariantImportSampleExport;
use Illuminate\Http\RedirectResponse;
use App\Support\ListingPerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VariantController extends Controller
{
    public function __construct(private VariantImportService $variantImporter) {}
    /**
     * Display a listing of variants.
     */
    public function index(Request $request)
    {
        $perPage = ListingPerPage::fromRequest($request);
        $variants = Variant::with('company')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);

        return view('variants.index', compact('variants', 'perPage'));
    }

    /**
     * Show the form for creating a new variant.
     */
    public function create()
    {
        $user = Auth::user();
        $suggestedCode = Variant::generateNextCode($user->company_id);

        return view('variants.create', compact('suggestedCode'));
    }

    /**
     * Store a newly created variant.
     */
    public function store(StoreVariantRequest $request)
    {
        $user = Auth::user();

        $options = Variant::resolveOptions($request->input('options'));

        $variant = Variant::create([
            'company_id' => $user->company_id,
            'name' => $request->name,
            'code' => Variant::resolveCode($user->company_id, $request->input('code')),
            'description' => $request->description,
            'options' => $options,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->has('is_active') ? true : false,
        ]);

        return redirect()
            ->route('variants.index')
            ->with('success', "Variant '{$variant->name}' created successfully.");
    }

    /**
     * Display the specified variant.
     */
    public function show(Variant $variant)
    {
        $variant->load('company', 'menuItems');
        return view('variants.show', compact('variant'));
    }

    /**
     * Show the form for editing the specified variant.
     */
    public function edit(Variant $variant)
    {
        return view('variants.edit', compact('variant'));
    }

    /**
     * Update the specified variant.
     */
    public function update(UpdateVariantRequest $request, Variant $variant)
    {
        $options = Variant::resolveOptions($request->input('options'));

        $code = trim((string) $request->input('code', ''));
        if ($code === '') {
            $code = $variant->code ?: Variant::generateNextCode($variant->company_id);
        }

        $variant->update([
            'name' => $request->name,
            'code' => $code,
            'description' => $request->description,
            'options' => $options,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->has('is_active') ? true : false,
        ]);

        return redirect()
            ->route('variants.index')
            ->with('success', "Variant '{$variant->name}' updated successfully.");
    }

    /**
     * Remove the specified variant.
     */
    public function destroy(Variant $variant)
    {
        $name = $variant->name;
        $variant->delete();

        return redirect()
            ->route('variants.index')
            ->with('success', "Variant '{$name}' deleted successfully.");
    }

    public function import(): View
    {
        return view('variants.import', [
            'expectedHeaders' => VariantImportService::expectedHeaders(),
            'importResult' => session('importResult'),
        ]);
    }

    public function importSample(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');

        return (new VariantImportSampleExport)->download($format);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');

        return (new VariantExport)->download($format);
    }

    public function importStore(ImportVariantsRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $result = $this->variantImporter->import($request->file('file'), (int) $user->company_id);

        $message = sprintf(
            'Import finished: %d created, %d updated.',
            $result['created'],
            $result['updated'],
        );

        if ($result['skipped'] > 0) {
            $message .= sprintf(' %d group(s) skipped.', $result['skipped']);
        }

        return redirect()
            ->route('variants.import')
            ->with('importResult', $result)
            ->with($result['created'] + $result['updated'] > 0 ? 'success' : 'error', $message);
    }
}
