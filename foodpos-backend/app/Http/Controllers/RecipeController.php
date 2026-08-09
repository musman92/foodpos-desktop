<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportRecipesRequest;
use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Support\IngredientQuantity;
use App\Support\TenantIngredientAccess;
use App\Services\RecipeImportService;
use App\Support\IngredientPicker;
use App\Support\ListingPerPage;
use App\Support\RecipeExport;
use App\Support\RecipeImportSampleExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecipeController extends Controller
{
    public function __construct(private RecipeImportService $recipeImporter) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $perPage = ListingPerPage::fromRequest($request);

        $query = Recipe::query()->withCount('items')->orderBy('name');

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)->orWhere('code', 'like', $like);
            });
        }

        $recipes = $query->paginate($perPage)->withQueryString();

        return view('recipes.index', compact('recipes', 'search', 'perPage'));
    }

    public function create(): View
    {
        $user = Auth::user();
        $suggestedCode = Recipe::generateNextCode($user->company_id);
        $ingredients = IngredientPicker::options(IngredientPicker::CONTEXT_RECIPE);
        $currency = get_company_config()['currency'] ?? 'USD';

        return view('recipes.create', compact('suggestedCode', 'ingredients', 'currency'));
    }

    public function store(StoreRecipeRequest $request): RedirectResponse
    {
        $user = Auth::user();

        $recipe = Recipe::create([
            'company_id' => $user->company_id,
            'name' => $request->name,
            'code' => Recipe::resolveCode($user->company_id, $request->input('code')),
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $recipe->syncItems($this->normalizeItems($request->input('items', [])));

        return redirect()
            ->route('recipes.index')
            ->with('success', "Recipe '{$recipe->name}' created successfully.");
    }

    public function show(Recipe $recipe): View
    {
        $recipe->load(['items.ingredient', 'menuItemsAsDefault', 'variantOptionLinks.menuItem']);

        return view('recipes.show', compact('recipe'));
    }

    public function edit(Recipe $recipe): View
    {
        $recipe->load('items.ingredient');
        $ingredients = IngredientPicker::options(IngredientPicker::CONTEXT_RECIPE);
        $currency = get_company_config()['currency'] ?? 'USD';

        return view('recipes.edit', compact('recipe', 'ingredients', 'currency'));
    }

    public function update(UpdateRecipeRequest $request, Recipe $recipe): RedirectResponse
    {
        $code = trim((string) $request->input('code', ''));
        if ($code === '') {
            $code = $recipe->code ?: Recipe::generateNextCode($recipe->company_id);
        }

        $recipe->update([
            'name' => $request->name,
            'code' => $code,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $recipe->syncItems($this->normalizeItems($request->input('items', [])));

        // Refresh linked menu item costs (default + option usages).
        $this->refreshLinkedMenuItemCosts($recipe);

        return redirect()
            ->route('recipes.index')
            ->with('success', "Recipe '{$recipe->name}' updated successfully.");
    }

    public function destroy(Recipe $recipe): RedirectResponse
    {
        $usage = $recipe->usageCount();
        if ($usage > 0) {
            return redirect()
                ->route('recipes.index')
                ->with('error', "Cannot delete '{$recipe->name}' — it is linked to {$usage} menu item recipe slot(s). Unlink it first.");
        }

        $name = $recipe->name;
        $recipe->items()->delete();
        $recipe->delete();

        return redirect()
            ->route('recipes.index')
            ->with('success', "Recipe '{$name}' deleted successfully.");
    }

    public function import(): View
    {
        return view('recipes.import', [
            'expectedHeaders' => RecipeImportService::expectedHeaders(),
            'importResult' => session('importResult'),
        ]);
    }

    public function importSample(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');

        return (new RecipeImportSampleExport)->download($format);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');

        return (new RecipeExport)->download($format);
    }

    public function importStore(ImportRecipesRequest $request): RedirectResponse
    {
        $user = Auth::user();

        if (! $user->company_id) {
            return redirect()
                ->route('recipes.import')
                ->with('error', 'Recipe import requires a company account.');
        }

        $result = $this->recipeImporter->import($request->file('file'), (int) $user->company_id);

        $message = sprintf(
            'Import finished: %d created, %d updated.',
            $result['created'],
            $result['updated'],
        );

        if ($result['skipped'] > 0) {
            $message .= sprintf(' %d group(s) skipped.', $result['skipped']);
        }

        return redirect()
            ->route('recipes.import')
            ->with('importResult', $result)
            ->with($result['created'] + $result['updated'] > 0 ? 'success' : 'error', $message);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $row) {
            $ingredientId = (int) ($row['ingredient_id'] ?? 0);
            if ($ingredientId <= 0) {
                continue;
            }

            $ingredient = Ingredient::with(['consumptionUnit', 'purchaseUnit'])
                ->where('company_id', Auth::user()->company_id)
                ->find($ingredientId);

            if (! $ingredient || ! TenantIngredientAccess::isUsableByCompany($ingredient, (int) Auth::user()->company_id)) {
                continue;
            }

            if (empty($row['unit_id'])) {
                $row['unit_id'] = IngredientQuantity::canonicalRecipeUnitId($ingredient);
            } elseif ($ingredient && IngredientQuantity::matchRecipeUnit($ingredient, (string) $row['unit_id']) === IngredientQuantity::UNIT_CONSUMPTION) {
                $row['unit_id'] = IngredientQuantity::canonicalRecipeUnitId($ingredient) ?? $row['unit_id'];
            }

            $normalized[] = $row;
        }

        return $normalized;
    }

    private function refreshLinkedMenuItemCosts(Recipe $recipe): void
    {
        $menuItemIds = $recipe->menuItemsAsDefault()->pluck('id')
            ->merge($recipe->variantOptionLinks()->pluck('menu_item_id'))
            ->unique()
            ->filter();

        if ($menuItemIds->isEmpty()) {
            return;
        }

        \App\Models\MenuItem::withoutGlobalScopes()
            ->where('company_id', $recipe->company_id)
            ->whereIn('id', $menuItemIds)
            ->get()
            ->each(function (\App\Models\MenuItem $menuItem) {
                if ($menuItem->type !== 'recipe') {
                    return;
                }
                $menuItem->cost = $menuItem->calculateCost();
                $menuItem->save();
            });
    }
}
