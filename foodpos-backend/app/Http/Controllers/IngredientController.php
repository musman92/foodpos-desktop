<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportIngredientsRequest;
use App\Http\Requests\StoreIngredientRequest;
use App\Http\Requests\UpdateIngredientRequest;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\IngredientUnit;
use App\Services\IngredientImportService;
use App\Support\CatalogListingQuery;
use App\Support\ListingPerPage;
use App\Support\IngredientExport;
use App\Support\IngredientImportSampleExport;
use App\Support\IngredientSku;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IngredientController extends Controller
{
    public function __construct(private IngredientImportService $ingredientImporter) {}

    public function index(Request $request)
    {
        $search = CatalogListingQuery::searchFromRequest($request);

        $perPage = ListingPerPage::fromRequest($request);

        $query = Ingredient::query()
            ->select([
                'id',
                'company_id',
                'category_id',
                'created_by',
                'purchase_unit_id',
                'consumption_unit_id',
                'name',
                'sku',
                'conversion_rate',
                'purchase_price',
                'cost_per_unit',
                'min_stock_level',
                'is_active',
            ])
            ->with([
                'category:id,name,code',
                'purchaseUnit:id,name,code',
                'consumptionUnit:id,name,code',
                'creator:id,name',
            ]);

        CatalogListingQuery::applySearch($query, $search, ['name', 'sku', 'description']);
        $query->orderByDesc('id');

        $ingredients = $query->paginate($perPage)->withQueryString();

        return view('ingredients.index', compact('ingredients', 'search', 'perPage'));
    }

    public function create()
    {
        $user = Auth::user();
        $categories = IngredientCategory::where('is_active', true)
            ->orderByRaw('company_id IS NULL ASC')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $units = IngredientUnit::orderBy('name')->get();
        $suggestedSku = IngredientSku::peekNext((int) $user->company_id);

        return view('ingredients.create', compact('categories', 'units', 'suggestedSku'));
    }

    public function store(StoreIngredientRequest $request)
    {
        $user = Auth::user();
        $consumptionUnit = IngredientUnit::findOrFail($request->consumption_unit_id);
        $purchasePrice = (float) $request->purchase_price;
        $conversionRate = (float) $request->conversion_rate;

        $ingredient = Ingredient::create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'category_id' => $request->category_id,
            'name' => $request->name,
            'sku' => IngredientSku::resolve((int) $user->company_id, $request->input('sku')),
            'purchase_unit_id' => $request->purchase_unit_id,
            'consumption_unit_id' => $consumptionUnit->id,
            'conversion_rate' => $conversionRate,
            'purchase_price' => $purchasePrice,
            'base_unit_id' => (string) $consumptionUnit->id,
            'cost_per_unit' => Ingredient::calculateCostPerUnit($purchasePrice, $conversionRate),
            'min_stock_level' => $request->min_stock_level ?? 0,
            'max_stock_level' => $request->max_stock_level,
            'track_stock' => $request->track_stock ?? 'yes',
            'is_active' => $request->has('is_active') ? true : false,
            'description' => $request->description,
        ]);

        return redirect()
            ->route('ingredients.index')
            ->with('success', "Ingredient '{$ingredient->name}' created successfully.");
    }

    public function show(Ingredient $ingredient)
    {
        $ingredient->load('company', 'category', 'consumptionUnit', 'purchaseUnit', 'creator');

        return view('ingredients.show', compact('ingredient'));
    }

    public function edit(Ingredient $ingredient)
    {
        $this->ensureTenantOwnedIngredient($ingredient);

        $categories = IngredientCategory::where('is_active', true)
            ->orderByRaw('company_id IS NULL ASC')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $units = IngredientUnit::orderBy('name')->get();

        return view('ingredients.edit', compact('ingredient', 'categories', 'units'));
    }

    public function update(UpdateIngredientRequest $request, Ingredient $ingredient)
    {
        $this->ensureTenantOwnedIngredient($ingredient);

        $consumptionUnit = IngredientUnit::findOrFail($request->consumption_unit_id);
        $purchasePrice = (float) $request->purchase_price;
        $conversionRate = (float) $request->conversion_rate;

        $sku = trim((string) $request->input('sku', ''));
        if ($sku === '') {
            $sku = $ingredient->sku ?: IngredientSku::resolve((int) $ingredient->company_id, null);
        }

        $ingredient->update([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'sku' => $sku,
            'purchase_unit_id' => $request->purchase_unit_id,
            'consumption_unit_id' => $consumptionUnit->id,
            'conversion_rate' => $conversionRate,
            'purchase_price' => $purchasePrice,
            'base_unit_id' => (string) $consumptionUnit->id,
            'cost_per_unit' => Ingredient::calculateCostPerUnit($purchasePrice, $conversionRate),
            'min_stock_level' => $request->min_stock_level ?? 0,
            'max_stock_level' => $request->max_stock_level,
            'track_stock' => $request->track_stock ?? 'yes',
            'is_active' => $request->has('is_active') ? true : false,
            'description' => $request->description,
        ]);

        return redirect()
            ->route('ingredients.index')
            ->with('success', "Ingredient '{$ingredient->name}' updated successfully.");
    }

    public function destroy(Ingredient $ingredient)
    {
        $this->ensureTenantOwnedIngredient($ingredient);

        $name = $ingredient->name;
        $ingredient->delete();

        return redirect()
            ->route('ingredients.index')
            ->with('success', "Ingredient '{$name}' deleted successfully.");
    }

    public function import(): View
    {
        return view('ingredients.import', [
            'expectedHeaders' => IngredientImportService::expectedHeaders(),
            'importResult' => session('importResult'),
        ]);
    }

    public function importSample(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');

        return (new IngredientImportSampleExport)->download($format);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');

        return (new IngredientExport)->download($format);
    }

    public function importStore(ImportIngredientsRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $result = $this->ingredientImporter->import(
            $request->file('file'),
            (int) $user->company_id,
            (int) $user->id,
        );

        $message = sprintf(
            'Import finished: %d created, %d updated.',
            $result['created'],
            $result['updated'],
        );

        if (($result['restored'] ?? 0) > 0) {
            $message .= sprintf(' %d restored from trash.', $result['restored']);
        }

        if ($result['skipped'] > 0) {
            $message .= sprintf(' %d row(s) skipped.', $result['skipped']);
        }

        return redirect()
            ->route('ingredients.import')
            ->with('importResult', $result)
            ->with($result['created'] + $result['updated'] > 0 ? 'success' : 'error', $message);
    }

    private function ensureTenantOwnedIngredient(Ingredient $ingredient): void
    {
        if ($ingredient->company_id === null) {
            abort(404);
        }
    }
}
