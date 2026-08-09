@extends('layouts.app')

@section('title', 'Stock')

@section('content')
<div class="space-y-6" x-data="{ showUnitPrice: false }">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Stock</h1>
            <p class="mt-1 text-sm text-gray-500">Current inventory for one branch at a time</p>
        </div>
    </div>

    @if($branches->count() > 0)
        <form method="GET" action="{{ route('inventory.index') }}" class="bg-white shadow rounded-lg p-4">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
                @if(show_branch_ui())
                <div class="w-full lg:w-56 shrink-0">
                    <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                    <select name="branch_id"
                            id="branch_id"
                            required
                            onchange="this.form.submit()"
                            class="block w-full h-11 px-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((int) $selectedBranchId === (int) $branch->id)>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @else
                    <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
                @endif
                <div class="w-full lg:w-52 shrink-0">
                    <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category_id"
                            id="category_id"
                            class="block w-full h-11 px-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All categories</option>
                        @foreach($ingredientCategories as $category)
                            <option value="{{ $category->id }}" @selected((int) ($categoryId ?? 0) === (int) $category->id)>
                                {{ $category->displayLabel() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-0 w-full">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="search"
                           name="search"
                           id="search"
                           value="{{ $search ?? '' }}"
                           placeholder="Name or several names separated by commas…"
                           class="block w-full h-11 px-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="flex items-end gap-2 shrink-0">
                    <button type="submit"
                            class="inline-flex items-center justify-center h-11 px-4 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        Apply
                    </button>
                    @if(!empty($search) || !empty($categoryId))
                        <a href="{{ route('inventory.index', ['branch_id' => $selectedBranchId]) }}"
                           class="inline-flex items-center justify-center h-11 px-4 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">
                            Clear
                        </a>
                    @endif
                </div>
            </div>
        </form>
    @endif

    <!-- Ingredient Stock -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Ingredient Stock</h2>
            <label class="flex items-center space-x-2 cursor-pointer">
                <input type="checkbox" x-model="showUnitPrice" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Show unit price</span>
            </label>
        </div>
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Ingredient</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Category</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Quantity</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap" x-show="showUnitPrice">Unit price</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap" x-show="!showUnitPrice">Avg cost</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @php
                        $groupedStock = $ingredientStock->groupBy('ingredient_id');
                    @endphp

                    @forelse($groupedStock as $ingredientId => $stocks)
                        @php
                            $stockSn = $loop->index + 1;
                            $firstStock = $stocks->first();
                            $ingredient = $firstStock->ingredient;
                            $totalConsumptionQty = $stocks->sum('quantity');
                            $totalPurchaseQty = $ingredient
                                ? $ingredient->toPurchaseQuantity((float) $totalConsumptionQty)
                                : $totalConsumptionQty;
                            $purchaseUnitName = $ingredient?->purchaseUnit?->displayLabel() ?? '—';
                            $weightedAvgConsumptionCost = $stocks->sum(fn ($s) => $s->quantity * $s->average_cost) / ($totalConsumptionQty > 0 ? $totalConsumptionQty : 1);
                            $weightedAvgPurchaseCost = $ingredient
                                ? $ingredient->costPerPurchaseUnit($weightedAvgConsumptionCost)
                                : $weightedAvgConsumptionCost;
                        @endphp

                        @foreach($stocks as $stock)
                            @php
                                $rowIngredient = $stock->ingredient;
                                $rowPurchaseQty = $rowIngredient
                                    ? $rowIngredient->toPurchaseQuantity((float) $stock->quantity)
                                    : (float) $stock->quantity;
                                $rowPurchaseCost = $rowIngredient
                                    ? $rowIngredient->costPerPurchaseUnit((float) $stock->average_cost)
                                    : (float) $stock->average_cost;
                            @endphp
                            <tr x-show="showUnitPrice" x-cloak class="hover:bg-gray-50 {{ ($rowIngredient && $stock->isLowStock()) ? 'bg-red-50' : '' }}">
                                <td class="px-3 py-3 whitespace-nowrap text-gray-500">{{ $loop->first ? $stockSn : '' }}</td>
                                <td class="px-3 py-3 whitespace-nowrap font-medium text-gray-900">
                                    {{ $rowIngredient->name ?? 'N/A' }}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-gray-500">
                                    {{ $rowIngredient?->category?->displayLabel() ?? '—' }}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-gray-900">
                                    @php
                                        $pq = fmod($rowPurchaseQty, 1.0) === 0.0
                                            ? number_format($rowPurchaseQty, 0)
                                            : rtrim(rtrim(number_format($rowPurchaseQty, 4, '.', ''), '0'), '.');
                                    @endphp
                                    <span class="font-medium text-gray-900">{{ $pq }}</span>
                                    <span class="text-gray-500">({{ $purchaseUnitName }})</span>
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-gray-900">
                                    {{ format_currency($rowPurchaseCost) }}
                                    <span class="text-xs text-gray-400">/ {{ $purchaseUnitName }}</span>
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    @if($rowIngredient && $stock->isLowStock())
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Low stock</span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">In stock</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach

                        <tr x-show="!showUnitPrice" class="hover:bg-gray-50 {{ ($ingredient && $firstStock->isLowStock()) ? 'bg-red-50' : '' }}">
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">{{ $stockSn }}</td>
                            <td class="px-3 py-3 whitespace-nowrap font-medium text-gray-900">
                                {{ $ingredient->name ?? 'N/A' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">
                                {{ $ingredient?->category?->displayLabel() ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900 font-semibold">
                                @php
                                    $tpq = fmod($totalPurchaseQty, 1.0) === 0.0
                                        ? number_format($totalPurchaseQty, 0)
                                        : rtrim(rtrim(number_format($totalPurchaseQty, 4, '.', ''), '0'), '.');
                                @endphp
                                <span class="font-semibold text-gray-900">{{ $tpq }}</span>
                                <span class="font-normal text-gray-500">({{ $purchaseUnitName }})</span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900">
                                {{ format_currency($weightedAvgPurchaseCost) }}
                                <span class="text-xs text-gray-400">/ {{ $purchaseUnitName }}</span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                @if($ingredient && $firstStock->isLowStock())
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Low stock</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">In stock</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">
                                @if($branches->isEmpty())
                                    No branches available.
                                @else
                                    No ingredient stock found for this branch.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Menu Item Stock -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">Menu Item Stock</h2>
            <label class="flex items-center space-x-2 cursor-pointer">
                <input type="checkbox" x-model="showUnitPrice" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm text-gray-700">Show unit price</span>
            </label>
        </div>
        <div class="overflow-x-auto">
            <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">SN</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Menu item</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Category</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Quantity</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap" x-show="showUnitPrice">Unit price</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap" x-show="!showUnitPrice">Avg price</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Expiry</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Last restocked</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @php
                        $groupedMenuItemStock = $menuItemStock->groupBy('menu_item_id');
                    @endphp

                    @forelse($groupedMenuItemStock as $menuItemId => $stocks)
                        @php
                            $menuStockSn = $loop->index + 1;
                            $firstStock = $stocks->first();
                            $menuItem = $firstStock->menuItem;
                            $totalQuantity = $stocks->sum('quantity');
                            $weightedAvgPrice = $stocks->sum(fn ($s) => $s->quantity * $s->unit_price) / ($totalQuantity > 0 ? $totalQuantity : 1);
                            $isLowStock = $menuItem && $selectedBranchId && $menuItem->isLowStockAtBranch((int) $selectedBranchId);
                        @endphp

                        @foreach($stocks as $stock)
                            <tr x-show="showUnitPrice" x-cloak class="hover:bg-gray-50 {{ $isLowStock ? 'bg-red-50' : '' }}">
                                <td class="px-3 py-3 whitespace-nowrap text-gray-500">{{ $loop->first ? $menuStockSn : '' }}</td>
                                <td class="px-3 py-3 whitespace-nowrap font-medium text-gray-900">
                                    {{ $stock->menuItem->name ?? 'N/A' }}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-gray-500">
                                    {{ $stock->menuItem?->category?->name ?? '—' }}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-gray-900">
                                    <span class="font-medium text-gray-900">{{ number_format($stock->quantity, 0) }}</span>
                                    <span class="text-gray-500">({{ $stock->menuItem?->sellUnitLabel() ?? '—' }})</span>
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-gray-900">
                                    {{ format_currency($stock->unit_price) }}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    @if($loop->first)
                                        @if($isLowStock)
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Low stock</span>
                                        @else
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">In stock</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-gray-500">
                                    {{ $stock->expiry_date ? format_date($stock->expiry_date) : '—' }}
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap text-gray-500">
                                    {{ $stock->last_restocked_at ? format_datetime($stock->last_restocked_at) : '—' }}
                                </td>
                            </tr>
                        @endforeach

                        <tr x-show="!showUnitPrice" class="hover:bg-gray-50 {{ $isLowStock ? 'bg-red-50' : '' }}">
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">{{ $menuStockSn }}</td>
                            <td class="px-3 py-3 whitespace-nowrap font-medium text-gray-900">
                                {{ $menuItem->name ?? 'N/A' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">
                                {{ $menuItem?->category?->name ?? '—' }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900 font-semibold">
                                <span class="font-semibold text-gray-900">{{ number_format($totalQuantity, 0) }}</span>
                                <span class="font-normal text-gray-500">({{ $menuItem?->sellUnitLabel() ?? '—' }})</span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-900">
                                {{ format_currency($weightedAvgPrice) }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                @if($isLowStock)
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Low stock</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">In stock</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">
                                {{ $stocks->pluck('expiry_date')->filter()->unique()->count() > 1 ? 'Multiple' : ($firstStock->expiry_date ? format_date($firstStock->expiry_date) : '—') }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-500">
                                {{ $firstStock->last_restocked_at ? format_datetime($firstStock->last_restocked_at) : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500">
                                No menu item stock found for this branch.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
