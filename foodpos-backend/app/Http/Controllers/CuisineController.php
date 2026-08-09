<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCuisineRequest;
use App\Http\Requests\UpdateCuisineRequest;
use App\Models\Cuisine;
use App\Services\ImageOptimizationService;
use App\Support\ListingPerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class CuisineController extends Controller
{
    public function __construct(private ImageOptimizationService $imageOptimizer) {}

    /**
     * Display a listing of cuisines.
     */
    public function index(Request $request)
    {
        $perPage = ListingPerPage::fromRequest($request);
        $cuisines = Cuisine::with(['company', 'menuItems'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);

        return view('cuisines.index', compact('cuisines', 'perPage'));
    }

    /**
     * Show the form for creating a new cuisine.
     */
    public function create()
    {
        return view('cuisines.create');
    }

    /**
     * Store a newly created cuisine.
     */
    public function store(StoreCuisineRequest $request)
    {
        $user = Auth::user();

        // Generate slug from name
        $slug = Str::slug($request->name);
        $originalSlug = $slug;
        $counter = 1;

        // Ensure unique slug within company
        while (Cuisine::where('company_id', $user->company_id)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $this->imageOptimizer->storeFromUpload($request->file('image'), 'cuisines', 'catalog');
        }

        $cuisine = Cuisine::create([
            'company_id' => $user->company_id,
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'image' => $imagePath,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->has('is_active') ? true : false,
        ]);

        return redirect()
            ->route('cuisines.index')
            ->with('success', "Cuisine '{$cuisine->name}' created successfully.");
    }

    /**
     * Display the specified cuisine.
     */
    public function show(Cuisine $cuisine)
    {
        $cuisine->load('company', 'menuItems');

        return view('cuisines.show', compact('cuisine'));
    }

    /**
     * Show the form for editing the specified cuisine.
     */
    public function edit(Cuisine $cuisine)
    {
        return view('cuisines.edit', compact('cuisine'));
    }

    /**
     * Update the specified cuisine.
     */
    public function update(UpdateCuisineRequest $request, Cuisine $cuisine)
    {
        // Generate slug from name if name changed
        $slug = $cuisine->slug;
        if ($request->name !== $cuisine->name) {
            $slug = Str::slug($request->name);
            $originalSlug = $slug;
            $counter = 1;

            // Ensure unique slug within company
            while (Cuisine::where('company_id', $cuisine->company_id)
                ->where('slug', $slug)
                ->where('id', '!=', $cuisine->id)
                ->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        // Handle image upload
        $imagePath = $cuisine->image;
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($cuisine->image) {
                Storage::disk('public')->delete($cuisine->image);
            }
            $imagePath = $this->imageOptimizer->storeFromUpload($request->file('image'), 'cuisines', 'catalog');
        }

        $cuisine->update([
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'image' => $imagePath,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->has('is_active') ? true : false,
        ]);

        return redirect()
            ->route('cuisines.index')
            ->with('success', "Cuisine '{$cuisine->name}' updated successfully.");
    }

    /**
     * Remove the specified cuisine.
     */
    public function destroy(Cuisine $cuisine)
    {
        $name = $cuisine->name;

        // Delete image if exists
        if ($cuisine->image) {
            Storage::disk('public')->delete($cuisine->image);
        }

        $cuisine->delete();

        return redirect()
            ->route('cuisines.index')
            ->with('success', "Cuisine '{$name}' deleted successfully.");
    }
}
