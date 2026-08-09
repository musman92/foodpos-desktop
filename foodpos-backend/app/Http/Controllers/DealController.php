<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\MenuItem;
use App\Services\ImageOptimizationService;
use App\Support\ListingPerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DealController extends Controller
{
    public function __construct(private ImageOptimizationService $imageOptimizer) {}

    public function index(Request $request)
    {
        $perPage = ListingPerPage::fromRequest($request);
        $deals = Deal::withCount('menuItems')
            ->orderBy('title')
            ->paginate($perPage);

        return view('deals.index', compact('deals', 'perPage'));
    }

    public function create()
    {
        $menuItems = MenuItem::orderBy('name')->get();

        return view('deals.create', compact('menuItems'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'price' => 'required|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'is_active' => 'boolean',
            'items' => 'nullable|array',
            'items.*.menu_item_id' => 'required_with:items|exists:menu_items,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.variant_id' => 'nullable|exists:variants,id',
            'items.*.option_name' => 'nullable|string|max:255',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $this->imageOptimizer->storeFromUpload($request->file('image'), 'deals', 'catalog');
        }

        $deal = Deal::create([
            'company_id' => $user->company_id,
            'title' => $request->title,
            'description' => $request->description,
            'image' => $imagePath,
            'price' => $request->price,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'start_time' => $request->start_time ?: null,
            'end_time' => $request->end_time ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($request->filled('items')) {
            $deal->menuItems()->detach();
            foreach ($request->items as $row) {
                if (empty($row['menu_item_id']) || (int) ($row['quantity'] ?? 0) < 1) {
                    continue;
                }
                $deal->menuItems()->attach($row['menu_item_id'], [
                    'quantity' => (int) $row['quantity'],
                    'variant_id' => !empty($row['variant_id']) ? $row['variant_id'] : null,
                    'option_name' => $row['option_name'] ?? null,
                    'unit_price' => isset($row['unit_price']) ? (float) $row['unit_price'] : null,
                ]);
            }
        }

        return redirect()->route('deals.index')->with('success', 'Deal created successfully.');
    }

    public function show(Deal $deal)
    {
        $deal->load('menuItems');
        return view('deals.show', compact('deal'));
    }

    public function edit(Deal $deal)
    {
        $deal->load('menuItems');
        $menuItems = MenuItem::orderBy('name')->get();

        return view('deals.edit', compact('deal', 'menuItems'));
    }

    /**
     * Return variants with option prices for a menu item (for deal form AJAX).
     */
    public function menuItemVariants(MenuItem $menuItem)
    {
        $menuItem->load('variants');
        $data = $this->buildMenuItemsWithVariants(collect([$menuItem]));
        return response()->json($data[0] ?? ['id' => $menuItem->id, 'name' => $menuItem->name, 'price' => (float) $menuItem->price, 'variants' => []]);
    }

    public function update(Request $request, Deal $deal)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'price' => 'required|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'is_active' => 'boolean',
            'items' => 'nullable|array',
            'items.*.menu_item_id' => 'required_with:items|exists:menu_items,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.variant_id' => 'nullable|exists:variants,id',
            'items.*.option_name' => 'nullable|string|max:255',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        $imagePath = $deal->image;
        if ($request->hasFile('image')) {
            if ($deal->image) {
                Storage::disk('public')->delete($deal->image);
            }
            $imagePath = $this->imageOptimizer->storeFromUpload($request->file('image'), 'deals', 'catalog');
        }

        $deal->update([
            'title' => $request->title,
            'description' => $request->description,
            'image' => $imagePath,
            'price' => $request->price,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'start_time' => $request->start_time ?: null,
            'end_time' => $request->end_time ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $deal->menuItems()->detach();
        if ($request->filled('items')) {
            foreach ($request->items as $row) {
                if (empty($row['menu_item_id']) || (int) ($row['quantity'] ?? 0) < 1) {
                    continue;
                }
                $deal->menuItems()->attach($row['menu_item_id'], [
                    'quantity' => (int) $row['quantity'],
                    'variant_id' => !empty($row['variant_id']) ? $row['variant_id'] : null,
                    'option_name' => $row['option_name'] ?? null,
                    'unit_price' => isset($row['unit_price']) ? (float) $row['unit_price'] : null,
                ]);
            }
        }

        return redirect()->route('deals.index')->with('success', 'Deal updated successfully.');
    }

    public function destroy(Deal $deal)
    {
        if ($deal->image) {
            Storage::disk('public')->delete($deal->image);
        }
        $deal->delete();
        return redirect()->route('deals.index')->with('success', 'Deal deleted.');
    }

    /**
     * Build menu items with variants and option prices for the deal form.
     */
    protected function buildMenuItemsWithVariants($menuItems)
    {
        return $menuItems->map(function ($item) {
            $variantsData = [];
            $item->load('variants'); // ensure variants are loaded
            foreach ($item->variants ?? [] as $variant) {
                $rawPrices = $variant->pivot->option_prices ?? null;
                if (is_string($rawPrices)) {
                    $rawPrices = json_decode($rawPrices, true);
                }
                $optionPrices = is_array($rawPrices) ? $rawPrices : [];
                $options = [];
                if ($variant->options && is_array($variant->options)) {
                    foreach ($variant->options as $opt) {
                        $optName = is_array($opt) ? ($opt['name'] ?? '') : (is_object($opt) ? ($opt->name ?? '') : '');
                        if ($optName === '') {
                            continue;
                        }
                        $price = isset($optionPrices[$optName]) ? (float) $optionPrices[$optName] : (float) ($variant->pivot->price ?? $item->price ?? 0);
                        $options[] = ['name' => $optName, 'price' => $price];
                    }
                }
                if (empty($options) && !empty($optionPrices)) {
                    foreach ($optionPrices as $optName => $price) {
                        if ($optName !== '' && $optName !== null) {
                            $options[] = ['name' => (string) $optName, 'price' => (float) $price];
                        }
                    }
                }
                if (!empty($options)) {
                    $variantsData[] = ['id' => $variant->id, 'name' => $variant->name, 'options' => $options];
                }
            }
            return [
                'id' => $item->id,
                'name' => $item->name,
                'price' => (float) $item->price,
                'variants' => $variantsData,
            ];
        })->values()->toArray();
    }
}
