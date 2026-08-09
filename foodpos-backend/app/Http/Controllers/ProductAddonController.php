<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportProductAddonsRequest;
use App\Http\Requests\StoreProductAddonRequest;
use App\Http\Requests\UpdateProductAddonRequest;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\ProductAddon;
use App\Models\ProductAddonRecipe;
use App\Services\ProductAddonImportService;
use App\Support\IngredientPicker;
use App\Support\IngredientQuantity;
use App\Support\ProductAddonExport;
use App\Support\ProductAddonImportSampleExport;
use Illuminate\Http\RedirectResponse;
use App\Support\ListingPerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductAddonController extends Controller
{
    public function __construct(private ProductAddonImportService $importer) {}

    public function index(Request $request)
    {
        $perPage = ListingPerPage::fromRequest($request);
        $productAddons = ProductAddon::with(['company', 'menuItem'])
            ->orderBy('name')
            ->paginate($perPage);

        return view('product-addons.index', compact('productAddons', 'perPage'));
    }

    public function create()
    {
        $user = Auth::user();

        return view('product-addons.create', $this->formData($user->company_id));
    }

    public function store(StoreProductAddonRequest $request)
    {
        $user = Auth::user();

        $productAddon = ProductAddon::create([
            'company_id' => $user->company_id,
            'code' => ProductAddon::resolveCode($user->company_id, $request->input('code')),
            'name' => $request->name,
            'price' => $request->price,
            'type' => $request->input('type', ProductAddon::TYPE_NONE),
            'track_inventory' => $request->boolean('track_inventory'),
            'menu_item_id' => $request->input('type') === ProductAddon::TYPE_SINGLE ? $request->menu_item_id : null,
            'tax_id' => null,
        ]);

        $this->syncRecipes($productAddon, $request);
        $this->refreshCost($productAddon);

        return redirect()
            ->route('product-addons.index')
            ->with('success', "Product addon '{$productAddon->name}' created successfully.");
    }

    public function show(ProductAddon $productAddon)
    {
        $productAddon->load('company', 'recipes.ingredient', 'menuItem');

        return view('product-addons.show', compact('productAddon'));
    }

    public function edit(ProductAddon $productAddon)
    {
        $user = Auth::user();
        $productAddon->load('recipes.ingredient', 'menuItem');

        return view('product-addons.edit', array_merge(
            ['productAddon' => $productAddon],
            $this->formData($user->company_id)
        ));
    }

    public function update(UpdateProductAddonRequest $request, ProductAddon $productAddon)
    {
        $type = $request->input('type', ProductAddon::TYPE_NONE);

        $productAddon->update([
            'code' => ProductAddon::resolveCode($productAddon->company_id, $request->input('code') ?: $productAddon->code),
            'name' => $request->name,
            'price' => $request->price,
            'type' => $type,
            'track_inventory' => $request->boolean('track_inventory'),
            'menu_item_id' => $type === ProductAddon::TYPE_SINGLE ? $request->menu_item_id : null,
            'tax_id' => null,
        ]);

        if ($type === ProductAddon::TYPE_RECIPE) {
            $productAddon->recipes()->delete();
            $this->syncRecipes($productAddon, $request);
        } else {
            $productAddon->recipes()->delete();
        }

        $this->refreshCost($productAddon);

        return redirect()
            ->route('product-addons.index')
            ->with('success', "Product addon '{$productAddon->name}' updated successfully.");
    }

    public function destroy(ProductAddon $productAddon)
    {
        $name = $productAddon->name;
        $productAddon->delete();

        return redirect()
            ->route('product-addons.index')
            ->with('success', "Product addon '{$name}' deleted successfully.");
    }

    public function import(): View
    {
        return view('product-addons.import', [
            'importResult' => session('importResult'),
        ]);
    }

    public function importSample(string $format): StreamedResponse
    {
        return (new ProductAddonImportSampleExport)->download($format);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');

        return (new ProductAddonExport)->download($format);
    }

    public function importStore(ImportProductAddonsRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $result = $this->importer->import($request->file('file'), (int) $user->company_id);

        $message = sprintf(
            'Import finished: %d created, %d updated, %d skipped.',
            $result['created'],
            $result['updated'],
            $result['skipped']
        );

        return redirect()
            ->route('product-addons.import')
            ->with('importResult', $result)
            ->with($result['errors'] === [] ? 'success' : 'error', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(int $companyId): array
    {
        return [
            'suggestedCode' => ProductAddon::generateNextCode($companyId),
            'ingredients' => IngredientPicker::options(IngredientPicker::CONTEXT_RECIPE),
            'singleMenuItems' => MenuItem::query()
                ->where('company_id', $companyId)
                ->where('type', 'single')
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'cost']),
            'currency' => auth()->user()?->company?->currency ?? 'USD',
        ];
    }

    private function syncRecipes(ProductAddon $productAddon, Request $request): void
    {
        if ($request->input('type') !== ProductAddon::TYPE_RECIPE || ! $request->has('recipes')) {
            return;
        }

        foreach ($request->recipes as $recipeData) {
            if (empty($recipeData['ingredient_id']) || empty($recipeData['quantity'])) {
                continue;
            }

            ProductAddonRecipe::create([
                'product_addon_id' => $productAddon->id,
                'ingredient_id' => $recipeData['ingredient_id'],
                'quantity' => $recipeData['quantity'],
                'unit_id' => $this->resolveRecipeUnitId($recipeData),
                'waste_percentage' => $recipeData['waste_percentage'] ?? 0,
                'notes' => $recipeData['notes'] ?? null,
            ]);
        }
    }

    private function resolveRecipeUnitId(array $recipeData): ?string
    {
        $ingredient = Ingredient::with(['consumptionUnit', 'purchaseUnit'])->find($recipeData['ingredient_id'] ?? null);
        if (! $ingredient) {
            return null;
        }

        $unitId = $recipeData['unit_id'] ?? null;
        if (! $unitId) {
            return IngredientQuantity::canonicalRecipeUnitId($ingredient);
        }

        if (! IngredientQuantity::isValidRecipeUnit($ingredient, (string) $unitId)) {
            return null;
        }

        if (IngredientQuantity::matchRecipeUnit($ingredient, (string) $unitId) === IngredientQuantity::UNIT_CONSUMPTION) {
            return IngredientQuantity::canonicalRecipeUnitId($ingredient);
        }

        return (string) $unitId;
    }

    private function refreshCost(ProductAddon $productAddon): void
    {
        $productAddon->unsetRelation('recipes');
        $productAddon->load(['recipes.ingredient', 'menuItem']);
        $productAddon->cost = $productAddon->calculateCost();
        $productAddon->save();
    }
}
