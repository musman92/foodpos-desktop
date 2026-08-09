<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportCategoriesRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryImportService;
use App\Services\ImageOptimizationService;
use App\Support\CatalogListingQuery;
use App\Support\CategoryExport;
use App\Support\CategoryImportSampleExport;
use App\Support\ListingPerPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CategoryController extends Controller
{
    public function __construct(
        private ImageOptimizationService $imageOptimizer,
        private CategoryImportService $categoryImporter,
    ) {}

    /**
     * Display a listing of categories.
     */
    public function index(Request $request)
    {
        $search = CatalogListingQuery::searchFromRequest($request);
        $perPage = ListingPerPage::fromRequest($request);

        $like = $search !== '' ? '%'.CatalogListingQuery::escapeLike($search).'%' : null;

        $query = Category::with([
            'company',
            'parent',
            'menuItems',
            'children' => function ($childQuery) use ($like) {
                if ($like) {
                    $childQuery->where(function ($q) use ($like) {
                        $q->where('name', 'like', $like)
                            ->orWhere('description', 'like', $like)
                            ->orWhere('slug', 'like', $like)
                            ->orWhere('code', 'like', $like);
                    });
                }
                $childQuery->orderBy('sort_order')->orderBy('name');
            },
            'children.menuItems',
        ])->whereNull('parent_id');

        if ($like) {
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhereHas('children', function ($childQuery) use ($like) {
                        $childQuery->where('name', 'like', $like)
                            ->orWhere('description', 'like', $like)
                            ->orWhere('slug', 'like', $like)
                            ->orWhere('code', 'like', $like);
                    });
            });
        }

        $query->orderBy('sort_order')->orderBy('name');

        $categories = $query->paginate($perPage)->withQueryString();

        return view('categories.index', compact('categories', 'search', 'perPage'));
    }

    /**
     * Show the form for creating a new category.
     */
    public function create()
    {
        $user = Auth::user();
        $parentCategories = Category::where('company_id', $user->company_id)
            ->whereNull('parent_id') // Only top-level categories can be parents
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $suggestedCode = Category::generateNextCode($user->company_id);

        return view('categories.create', compact('parentCategories', 'suggestedCode'));
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreCategoryRequest $request)
    {
        $user = Auth::user();

        // Generate slug from name
        $slug = Str::slug($request->name);
        $originalSlug = $slug;
        $counter = 1;

        // Ensure unique slug within company
        while (Category::where('company_id', $user->company_id)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $this->imageOptimizer->storeFromUpload($request->file('image'), 'categories', 'catalog');
        }

        // Validate parent_id to prevent circular references
        $parentId = $request->parent_id;
        if ($parentId) {
            $parent = Category::where('company_id', $user->company_id)
                ->where('id', $parentId)
                ->whereNull('parent_id') // Only top-level categories can be parents
                ->first();
            
            if (!$parent) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors(['parent_id' => 'Invalid parent category selected.']);
            }
        }

        $category = Category::create([
            'company_id' => $user->company_id,
            'parent_id' => $parentId,
            'name' => $request->name,
            'code' => Category::resolveCode($user->company_id, $request->input('code')),
            'slug' => $slug,
            'description' => $request->description,
            'image' => $imagePath,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->has('is_active') ? true : false,
        ]);

        return redirect()
            ->route('categories.index')
            ->with('success', "Category '{$category->name}' created successfully.");
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category)
    {
        $category->load('company', 'parent', 'children', 'menuItems');

        return view('categories.show', compact('category'));
    }

    /**
     * Show the form for editing the specified category.
     */
    public function edit(Category $category)
    {
        $this->ensureTenantOwnedCategory($category);

        $user = Auth::user();
        $parentCategories = Category::where('company_id', $user->company_id)
            ->where('id', '!=', $category->id) // Exclude current category
            ->whereNull('parent_id') // Only top-level categories can be parents
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('categories.edit', compact('category', 'parentCategories'));
    }

    /**
     * Update the specified category.
     */
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $this->ensureTenantOwnedCategory($category);

        // Generate slug from name if name changed
        $slug = $category->slug;
        if ($request->name !== $category->name) {
            $slug = Str::slug($request->name);
            $originalSlug = $slug;
            $counter = 1;

            // Ensure unique slug within company
            while (Category::where('company_id', $category->company_id)
                ->where('slug', $slug)
                ->where('id', '!=', $category->id)
                ->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        // Validate parent_id to prevent circular references
        $parentId = $request->parent_id;
        if ($parentId) {
            // Prevent setting itself or its children as parent
            if ($parentId == $category->id) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors(['parent_id' => 'A category cannot be its own parent.']);
            }

            // Check if parent is a descendant (would create circular reference)
            $parent = Category::where('company_id', $category->company_id)
                ->where('id', $parentId)
                ->whereNull('parent_id') // Only top-level categories can be parents
                ->first();
            
            if (!$parent) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors(['parent_id' => 'Invalid parent category selected.']);
            }

            // Check if the selected parent is a child of this category (circular reference)
            $isDescendant = $this->isDescendant($category, $parentId);
            if ($isDescendant) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors(['parent_id' => 'Cannot set a child category as parent (circular reference).']);
            }
        }

        // Handle image upload
        $imagePath = $category->image;
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $imagePath = $this->imageOptimizer->storeFromUpload($request->file('image'), 'categories', 'catalog');
        }

        $code = trim((string) $request->input('code', ''));
        if ($code === '') {
            $code = $category->code ?: Category::generateNextCode($category->company_id);
        }

        $category->update([
            'name' => $request->name,
            'code' => $code,
            'parent_id' => $parentId,
            'slug' => $slug,
            'description' => $request->description,
            'image' => $imagePath,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->has('is_active') ? true : false,
        ]);

        return redirect()
            ->route('categories.index')
            ->with('success', "Category '{$category->name}' updated successfully.");
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category)
    {
        $this->ensureTenantOwnedCategory($category);

        $name = $category->name;

        // Delete image if exists
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        return redirect()
            ->route('categories.index')
            ->with('success', "Category '{$name}' deleted successfully.");
    }

    /**
     * Show bulk import form.
     */
    public function import(): View
    {
        return view('categories.import', [
            'expectedHeaders' => CategoryImportService::expectedHeaders(),
            'importResult' => session('importResult'),
        ]);
    }

    /**
     * Download sample import file.
     */
    public function importSample(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');

        return (new CategoryImportSampleExport)->download($format);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');

        return (new CategoryExport)->download($format);
    }

    /**
     * Process bulk import upload.
     */
    public function importStore(ImportCategoriesRequest $request): RedirectResponse
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
            ->route('categories.import')
            ->with('importResult', $result)
            ->with($result['created'] + $result['updated'] > 0 ? 'success' : 'error', $message);
    }

    /**
     * Check if a category ID is a descendant of the given category.
     */
    private function isDescendant(Category $category, int $potentialParentId): bool
    {
        $children = $category->children;
        
        foreach ($children as $child) {
            if ($child->id == $potentialParentId) {
                return true;
            }
            
            if ($this->isDescendant($child, $potentialParentId)) {
                return true;
            }
        }
        
        return false;
    }

    private function ensureTenantOwnedCategory(Category $category): void
    {
        if ($category->company_id === null) {
            abort(404);
        }
    }
}
