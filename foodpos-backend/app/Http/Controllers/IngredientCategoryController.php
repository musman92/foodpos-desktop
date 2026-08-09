<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportIngredientCategoriesRequest;
use App\Http\Requests\StoreIngredientCategoryRequest;
use App\Http\Requests\UpdateIngredientCategoryRequest;
use App\Models\IngredientCategory;
use App\Services\IngredientCategoryImportService;
use App\Support\CatalogListingQuery;
use App\Support\ListingPerPage;
use App\Support\IngredientCategoryExport;
use App\Support\IngredientCategoryImportSampleExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IngredientCategoryController extends Controller
{
    public function __construct(private IngredientCategoryImportService $categoryImporter) {}

    public function index(Request $request)
    {
        $search = CatalogListingQuery::searchFromRequest($request);

        $perPage = ListingPerPage::fromRequest($request);

        $query = IngredientCategory::query()
            ->select(['id', 'company_id', 'name', 'code', 'description', 'sort_order', 'is_active'])
            ->withCount('ingredients');

        CatalogListingQuery::applySearch($query, $search, ['name', 'description', 'code']);
        $query->orderBy('sort_order')->orderBy('name');

        $categories = $query->paginate($perPage)->withQueryString();

        return view('ingredient-categories.index', compact('categories', 'search', 'perPage'));
    }

    public function create()
    {
        $user = Auth::user();
        $suggestedCode = IngredientCategory::generateNextCode($user->company_id);

        return view('ingredient-categories.create', compact('suggestedCode'));
    }

    public function store(StoreIngredientCategoryRequest $request)
    {
        $user = Auth::user();

        $category = IngredientCategory::create([
            'company_id' => $user->company_id,
            'name' => $request->name,
            'code' => IngredientCategory::resolveCode($user->company_id, $request->input('code')),
            'description' => $request->description,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->has('is_active') ? true : false,
        ]);

        return redirect()
            ->route('ingredient-categories.index')
            ->with('success', "Ingredient category '{$category->name}' created successfully.");
    }

    public function show(IngredientCategory $ingredientCategory)
    {
        $ingredientCategory->load('company', 'ingredients');

        return view('ingredient-categories.show', compact('ingredientCategory'));
    }

    public function edit(IngredientCategory $ingredientCategory)
    {
        $this->ensureTenantOwnedCategory($ingredientCategory);

        return view('ingredient-categories.edit', compact('ingredientCategory'));
    }

    public function update(UpdateIngredientCategoryRequest $request, IngredientCategory $ingredientCategory)
    {
        $this->ensureTenantOwnedCategory($ingredientCategory);

        $code = trim((string) $request->input('code', ''));
        if ($code === '') {
            $code = $ingredientCategory->code ?: IngredientCategory::generateNextCode($ingredientCategory->company_id);
        }

        $ingredientCategory->update([
            'name' => $request->name,
            'code' => $code,
            'description' => $request->description,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->has('is_active') ? true : false,
        ]);

        return redirect()
            ->route('ingredient-categories.index')
            ->with('success', "Ingredient category '{$ingredientCategory->name}' updated successfully.");
    }

    public function destroy(IngredientCategory $ingredientCategory)
    {
        $this->ensureTenantOwnedCategory($ingredientCategory);

        $name = $ingredientCategory->name;
        $ingredientCategory->delete();

        return redirect()
            ->route('ingredient-categories.index')
            ->with('success', "Ingredient category '{$name}' deleted successfully.");
    }

    public function import(): View
    {
        return view('ingredient-categories.import', [
            'expectedHeaders' => IngredientCategoryImportService::expectedHeaders(),
            'importResult' => session('importResult'),
        ]);
    }

    public function importSample(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');

        return (new IngredientCategoryImportSampleExport)->download($format);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');

        return (new IngredientCategoryExport)->download($format);
    }

    public function importStore(ImportIngredientCategoriesRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $result = $this->categoryImporter->import($request->file('file'), (int) $user->company_id);

        $message = sprintf(
            'Import finished: %d created, %d updated.',
            $result['created'],
            $result['updated'],
        );

        if ($result['skipped'] > 0) {
            $message .= sprintf(' %d row(s) skipped.', $result['skipped']);
        }

        return redirect()
            ->route('ingredient-categories.import')
            ->with('importResult', $result)
            ->with($result['created'] + $result['updated'] > 0 ? 'success' : 'error', $message);
    }

    private function ensureTenantOwnedCategory(IngredientCategory $category): void
    {
        if ($category->company_id === null) {
            abort(404);
        }
    }
}
