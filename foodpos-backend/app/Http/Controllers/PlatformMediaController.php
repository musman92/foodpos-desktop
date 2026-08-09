<?php

namespace App\Http\Controllers;

use App\Helpers\PlatformMediaCatalog;
use App\Http\Requests\StorePlatformMediaRequest;
use App\Models\MenuItem;
use App\Models\PlatformMedia;
use App\Services\ImageOptimizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PlatformMediaController extends Controller
{
    public function __construct(private ImageOptimizationService $imageOptimizer) {}

    public function index(Request $request): View
    {
        $this->authorizeSuperAdmin();

        $categoryFilter = $request->get('category');

        $media = PlatformMedia::with('uploader')
            ->when($categoryFilter, fn ($q) => $q->where('category', $categoryFilter))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(24)
            ->withQueryString();

        $categoryOptions = PlatformMediaCatalog::categories();
        $categoriesWithCounts = PlatformMediaCatalog::categoriesWithCounts();

        return view('platform-media.index', compact('media', 'categoryFilter', 'categoryOptions', 'categoriesWithCounts'));
    }

    public function store(StorePlatformMediaRequest $request): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        $file = $request->file('image');
        $storedPath = $this->imageOptimizer->storeFromUpload($file, 'platform-media', 'platform_media');

        $title = $request->filled('title')
            ? $request->title
            : pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        PlatformMedia::create([
            'title' => $title,
            'file_path' => $storedPath,
            'category' => $request->category,
            'alt_text' => $request->alt_text ?: $title,
            'sort_order' => $request->sort_order ?? 0,
            'is_active' => $request->boolean('is_active', true),
            'uploaded_by' => Auth::id(),
        ]);

        return redirect()
            ->route('platform-media.index', ['category' => $request->category])
            ->with('success', 'Image added to the platform media library.');
    }

    public function destroy(PlatformMedia $platform_medium): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        $inUse = MenuItem::withoutGlobalScopes()
            ->where('image', $platform_medium->file_path)
            ->exists();

        if ($inUse) {
            return redirect()
                ->route('platform-media.index')
                ->with('error', 'This image is used by one or more menu items and cannot be deleted.');
        }

        if (Storage::disk('public')->exists($platform_medium->file_path)) {
            Storage::disk('public')->delete($platform_medium->file_path);
        }

        $platform_medium->delete();

        return redirect()
            ->route('platform-media.index')
            ->with('success', 'Image removed from the platform media library.');
    }

    public function browse(Request $request): JsonResponse
    {
        $query = PlatformMedia::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('title');

        if ($category = trim((string) $request->get('category', ''))) {
            $query->where('category', $category);
        }

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate(24);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (PlatformMedia $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'category' => $item->category,
                'file_path' => $item->file_path,
                'url' => $item->url,
                'alt_text' => $item->alt_text,
            ])->values(),
            'categories' => PlatformMediaCatalog::categoriesWithCounts(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    private function authorizeSuperAdmin(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403, 'Only platform administrators can manage the media library.');
    }
}
