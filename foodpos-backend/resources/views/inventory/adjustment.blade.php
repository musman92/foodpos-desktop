@extends('layouts.app')

@section('title', isset($editingMovement) && $editingMovement ? 'Edit inventory adjustment' : 'Inventory adjustment')

@section('content')
@php
    $isEditing = isset($editingMovement) && $editingMovement;
    $defaultAdjustable = old('adjustable', $isEditing
        ? ($editingMovement->ingredient_id ? 'ingredient' : 'menu_item')
        : 'ingredient');
    $defaultIngredientId = old('ingredient_id', $isEditing ? $editingMovement->ingredient_id : null);
    $defaultMenuItemId = old('menu_item_id', $isEditing ? $editingMovement->menu_item_id : null);
    $defaultMode = $isEditing ? 'change' : old('mode', 'change');
    $defaultUnit = $isEditing ? 'consumption' : old('unit', 'consumption');
    $defaultQuantity = old('quantity', $isEditing
        ? ($editingMovement->movement === 'in'
            ? (string) (float) $editingMovement->quantity
            : (string) (-1 * (float) $editingMovement->quantity))
        : null);
    $defaultNotes = old('notes', $isEditing ? $editingMovement->notes : '');
    $excludeMovementId = $isEditing ? (int) $editingMovement->id : null;
    $itemLabel = $isEditing
        ? ($editingMovement->ingredient_id
            ? ($editingMovement->ingredient->name ?? 'Ingredient')
            : ($editingMovement->menuItem->name ?? 'Menu item'))
        : null;

    $menuItemsForJs = $menuItems->map(fn ($mi) => [
        'id' => (int) $mi->id,
        'name' => $mi->name,
        'label' => $mi->name,
        'has_dual_units' => false,
        'consumption_unit_name' => 'pcs',
        'purchase_unit_name' => 'pcs',
        'conversion_rate' => 1,
    ])->values();
@endphp
<script>
    window.inventoryAdjustmentForm = function (ingredients, menuItems, adjustable, ingredientId, menuItemId, mode, unit, quantity, stockUrl, excludeMovementId) {
        ingredientId = ingredientId != null && ingredientId !== '' ? String(ingredientId) : '';
        menuItemId = menuItemId != null && menuItemId !== '' ? String(menuItemId) : '';
        excludeMovementId = excludeMovementId != null && excludeMovementId !== '' ? String(excludeMovementId) : '';

        return {
            ingredients,
            menuItems,
            adjustable,
            ingredientId,
            menuItemId,
            mode: mode || 'change',
            unit: unit || 'consumption',
            quantity: quantity != null && quantity !== '' ? String(quantity) : '',
            branchId: '',
            stockUrl,
            excludeMovementId,
            stockLoading: false,
            stockError: '',
            submitError: '',
            stock: null,
            init() {
                const branchSelect = document.getElementById('branch_id');
                this.branchId = branchSelect ? String(branchSelect.value || '') : '';
                if (branchSelect) {
                    branchSelect.addEventListener('change', () => {
                        this.branchId = String(branchSelect.value || '');
                        this.fetchStock();
                    });
                }

                this.$watch('adjustable', () => {
                    if (this.excludeMovementId) return;
                    this.submitError = '';
                    this.stockError = '';
                    this.stock = null;
                    if (this.adjustable === 'ingredient') {
                        this.menuItemId = '';
                    } else {
                        this.ingredientId = '';
                        this.unit = 'consumption';
                    }
                    this.fetchStock();
                });
                this.$watch('ingredientId', () => {
                    if (this.excludeMovementId) return;
                    this.fetchStock();
                });
                this.$watch('menuItemId', () => {
                    if (this.excludeMovementId) return;
                    this.fetchStock();
                });
                this.$watch('mode', () => {
                    this.submitError = '';
                    if (this.mode === 'exact' && this.quantity !== '' && Number(this.quantity) < 0) {
                        this.quantity = String(Math.abs(Number(this.quantity)));
                    }
                });
                this.$watch('unit', () => { this.submitError = ''; });

                this.fetchStock();
            },
            selectedOption() {
                if (this.adjustable === 'ingredient') {
                    return (this.ingredients || []).find((i) => String(i.id) === String(this.ingredientId)) || null;
                }
                return (this.menuItems || []).find((i) => String(i.id) === String(this.menuItemId)) || null;
            },
            hasDualUnits() {
                if (this.adjustable !== 'ingredient') return false;
                if (this.stock) return !!this.stock.has_dual_units;
                const opt = this.selectedOption();
                if (!opt) return false;
                const rate = Number(opt.conversion_rate || 1);
                return rate > 0 && Math.abs(rate - 1) > 0.0001
                    && opt.purchase_unit_name
                    && opt.consumption_unit_name
                    && String(opt.purchase_unit_id || '') !== String(opt.consumption_unit_id || '');
            },
            unitLabel(which) {
                if (this.stock) {
                    return which === 'purchase'
                        ? (this.stock.purchase_unit_name || 'Purchase')
                        : (this.stock.consumption_unit_name || 'Consumption');
                }
                const opt = this.selectedOption();
                if (!opt) return which === 'purchase' ? 'Purchase' : 'Consumption';
                return which === 'purchase'
                    ? (opt.purchase_unit_name || 'Purchase')
                    : (opt.consumption_unit_name || 'Consumption');
            },
            activeUnitLabel() {
                if (this.adjustable === 'menu_item') return 'pcs';
                return this.unit === 'purchase' ? this.unitLabel('purchase') : this.unitLabel('consumption');
            },
            currentInActiveUnit() {
                if (!this.stock) return null;
                return this.unit === 'purchase' && this.hasDualUnits()
                    ? Number(this.stock.quantity_purchase)
                    : Number(this.stock.quantity_consumption);
            },
            conversionRate() {
                if (this.stock) return Number(this.stock.conversion_rate || 1) || 1;
                const opt = this.selectedOption();
                return Number(opt?.conversion_rate || 1) || 1;
            },
            inputAsConsumption() {
                const raw = Number(this.quantity);
                if (!Number.isFinite(raw)) return null;
                if (this.adjustable === 'ingredient' && this.unit === 'purchase' && this.hasDualUnits()) {
                    return raw * this.conversionRate();
                }
                return raw;
            },
            previewDelta() {
                const asConsumption = this.inputAsConsumption();
                if (asConsumption === null || !this.stock) return null;
                if (this.mode === 'exact') {
                    return asConsumption - Number(this.stock.quantity_consumption);
                }
                return asConsumption;
            },
            previewNewStock() {
                if (!this.stock) return null;
                const delta = this.previewDelta();
                if (delta === null) return null;
                return Number(this.stock.quantity_consumption) + delta;
            },
            formatQty(value) {
                if (value === null || value === undefined || !Number.isFinite(Number(value))) return '—';
                const n = Number(value);
                if (Math.abs(n - Math.round(n)) < 0.0001) return n.toLocaleString(undefined, { maximumFractionDigits: 0 });
                return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 });
            },
            formatWithUnit(consumptionQty) {
                if (consumptionQty === null || !this.stock) return '—';
                const cons = this.formatQty(consumptionQty) + ' ' + (this.stock.consumption_unit_name || '');
                if (this.stock.has_dual_units) {
                    const purchase = Number(consumptionQty) / (Number(this.stock.conversion_rate) || 1);
                    return cons + ' (' + this.formatQty(purchase) + ' ' + (this.stock.purchase_unit_name || '') + ')';
                }
                return cons;
            },
            async fetchStock() {
                this.stockError = '';
                const hasItem = this.adjustable === 'ingredient' ? !!this.ingredientId : !!this.menuItemId;
                if (!this.branchId || !hasItem) {
                    this.stock = null;
                    return;
                }

                this.stockLoading = true;
                try {
                    const params = new URLSearchParams({
                        branch_id: this.branchId,
                        adjustable: this.adjustable,
                    });
                    if (this.adjustable === 'ingredient') {
                        params.set('ingredient_id', this.ingredientId);
                    } else {
                        params.set('menu_item_id', this.menuItemId);
                    }
                    if (this.excludeMovementId) {
                        params.set('exclude_movement_id', this.excludeMovementId);
                    }
                    const response = await fetch(this.stockUrl + '?' + params.toString(), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) {
                        const data = await response.json().catch(() => ({}));
                        throw new Error(data.message || 'Could not load current stock.');
                    }
                    this.stock = await response.json();
                    if (!this.stock.has_dual_units) {
                        this.unit = 'consumption';
                    }
                } catch (e) {
                    this.stock = null;
                    this.stockError = e.message || 'Could not load current stock.';
                } finally {
                    this.stockLoading = false;
                }
            },
            validateBeforeSubmit(event) {
                this.submitError = '';
                if (this.adjustable === 'ingredient' && !this.ingredientId) {
                    event.preventDefault();
                    this.submitError = 'Select an ingredient from the list.';
                    return false;
                }
                if (this.adjustable === 'menu_item' && !this.menuItemId) {
                    event.preventDefault();
                    this.submitError = 'Select a menu item from the list.';
                    return false;
                }
                const qty = Number(this.quantity);
                if (!Number.isFinite(qty)) {
                    event.preventDefault();
                    this.submitError = 'Enter a valid quantity.';
                    return false;
                }
                if (this.mode === 'change' && Math.abs(qty) < 0.01) {
                    event.preventDefault();
                    this.submitError = 'Quantity change must be at least 0.01 (use a negative value to decrease).';
                    return false;
                }
                if (this.mode === 'exact' && qty < 0) {
                    event.preventDefault();
                    this.submitError = 'Exact quantity cannot be negative.';
                    return false;
                }
                const delta = this.previewDelta();
                if (delta !== null && Math.abs(delta) < 0.01) {
                    event.preventDefault();
                    this.submitError = this.mode === 'exact'
                        ? 'Exact quantity matches current stock — nothing to adjust.'
                        : 'Quantity change must be non-zero.';
                    return false;
                }
                return true;
            },
        };
    };

    window._inventoryAdjustmentBoot = [
        @json($ingredients),
        @json($menuItemsForJs),
        @json($defaultAdjustable),
        @json($defaultIngredientId),
        @json($defaultMenuItemId),
        @json($defaultMode),
        @json($defaultUnit),
        @json($defaultQuantity),
        @json(route('inventory.adjustment.stock')),
        @json($excludeMovementId),
    ];
</script>
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $isEditing ? 'Edit inventory adjustment' : 'New inventory adjustment' }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                @if($isEditing)
                    Update the quantity change or notes. Stock is recalculated from the revised change. Branch, item, and adjustment type cannot be changed.
                @else
                    Increase or decrease stock at a branch: either an ingredient that tracks stock, or a <strong>single-type</strong> menu item that tracks inventory. Recipe items use ingredient adjustments instead. A stock movement record is created with your notes.
                @endif
            </p>
        </div>
        <a href="{{ route('inventory.adjustment.index') }}" class="inline-flex items-center justify-center h-11 px-4 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50 shrink-0">View history</a>
    </div>

    @if(session('error'))
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div
        class="bg-white shadow rounded-lg border border-gray-100 p-6 sm:p-8"
        x-data="window.inventoryAdjustmentForm(
            window._inventoryAdjustmentBoot[0],
            window._inventoryAdjustmentBoot[1],
            window._inventoryAdjustmentBoot[2],
            window._inventoryAdjustmentBoot[3],
            window._inventoryAdjustmentBoot[4],
            window._inventoryAdjustmentBoot[5],
            window._inventoryAdjustmentBoot[6],
            window._inventoryAdjustmentBoot[7],
            window._inventoryAdjustmentBoot[8],
            window._inventoryAdjustmentBoot[9]
        )"
    >
        <form
            method="POST"
            action="{{ $isEditing ? route('inventory.adjustment.update', $editingMovement) : route('inventory.adjustment.store') }}"
            class="space-y-6"
            @submit="validateBeforeSubmit($event)"
        >
            @csrf
            @if($isEditing)
                @method('PUT')
            @endif

            @if($isEditing)
                @unless(offline_edition())
                <div>
                    <span class="block text-sm font-medium text-gray-700 mb-1">Branch</span>
                    <p class="text-sm text-gray-900">{{ $editingMovement->branch->name ?? '—' }}</p>
                    <input type="hidden" name="branch_id" id="branch_id" value="{{ $editingMovement->branch_id }}">
                </div>
                @else
                    <input type="hidden" name="branch_id" id="branch_id" value="{{ $editingMovement->branch_id }}">
                @endunless
                <div>
                    <span class="block text-sm font-medium text-gray-700 mb-1">Item</span>
                    <p class="text-sm text-gray-900">
                        <span class="text-gray-500 text-xs uppercase mr-1">{{ $defaultAdjustable === 'ingredient' ? 'Ingredient' : 'Menu' }}</span>
                        {{ $itemLabel }}
                    </p>
                    <input type="hidden" name="adjustable" :value="adjustable">
                    <input type="hidden" name="ingredient_id" :value="adjustable === 'ingredient' ? ingredientId : ''">
                    <input type="hidden" name="menu_item_id" :value="adjustable === 'menu_item' ? menuItemId : ''">
                </div>
            @else
                @if(show_branch_ui())
                <div>
                    <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">Branch <span class="text-red-500">*</span></label>
                    <select name="branch_id" id="branch_id" required class="block w-full filter-control text-gray-900">
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" {{ (string) old('branch_id', $selectedBranchId) === (string) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                    @error('branch_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                @else
                    <input type="hidden" name="branch_id" id="branch_id" value="{{ old('branch_id', $selectedBranchId) }}">
                @endif

                <fieldset class="space-y-2">
                    <legend class="text-sm font-medium text-gray-700">Adjust <span class="text-red-500">*</span></legend>
                    <div class="flex flex-wrap gap-4 pt-0.5">
                        <label class="inline-flex items-center gap-2.5 h-11 text-sm font-medium text-gray-800 cursor-pointer pr-2">
                            <input type="radio" name="adjustable" value="ingredient" x-model="adjustable" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            Ingredient
                        </label>
                        <label class="inline-flex items-center gap-2.5 h-11 text-sm font-medium text-gray-800 cursor-pointer pr-2">
                            <input type="radio" name="adjustable" value="menu_item" x-model="adjustable" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            Menu item (single, tracked)
                        </label>
                    </div>
                    @error('adjustable')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </fieldset>

                <input type="hidden" name="ingredient_id" :value="adjustable === 'ingredient' ? ingredientId : ''">
                <input type="hidden" name="menu_item_id" :value="adjustable === 'menu_item' ? menuItemId : ''">

                <div x-show="adjustable === 'ingredient'" x-cloak
                     x-data="searchableSelect({
                         options: ingredients,
                         value: ingredientId,
                         showGlobalBadge: false,
                         maxResults: 150,
                         placeholder: 'Search ingredients…',
                         onChange: (value) => {
                             ingredientId = value ? String(value) : '';
                             submitError = '';
                         },
                     })"
                     x-init="init(); $watch('selectedValue', (value) => { ingredientId = value ? String(value) : ''; })">
                    <x-searchable-select
                        label="Ingredient"
                        :required="true"
                        compact
                        useButtonOptions
                        id="ingredient_search"
                    />
                    @if(count($ingredients) === 0)
                        <p class="mt-1 text-xs text-amber-700">No ingredients that track stock are available.</p>
                    @endif
                    @error('ingredient_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div x-show="adjustable === 'menu_item'" x-cloak
                     x-data="searchableSelect({
                         options: menuItems,
                         value: menuItemId,
                         maxResults: 150,
                         placeholder: 'Search menu items…',
                         onChange: (value) => {
                             menuItemId = value ? String(value) : '';
                             submitError = '';
                         },
                     })"
                     x-init="init(); $watch('selectedValue', (value) => { menuItemId = value ? String(value) : ''; })">
                    <x-searchable-select
                        label="Menu item"
                        :required="true"
                        compact
                        useButtonOptions
                        id="menu_item_search"
                    />
                    @if($menuItems->isEmpty())
                        <p class="mt-1 text-xs text-amber-700">No single-type tracked menu items are available.</p>
                    @endif
                    @error('menu_item_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            @endif

            <div x-show="(adjustable === 'ingredient' && ingredientId) || (adjustable === 'menu_item' && menuItemId)" x-cloak
                 class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 mb-1"
                   x-text="excludeMovementId ? 'Stock before this adjustment' : 'Current stock'"></p>
                <p x-show="stockLoading" class="text-sm text-gray-500">Loading…</p>
                <p x-show="!stockLoading && stockError" class="text-sm text-red-600" x-text="stockError"></p>
                <p x-show="!stockLoading && !stockError && stock" class="text-sm font-semibold text-gray-900" x-text="formatWithUnit(stock.quantity_consumption)"></p>
            </div>

            @if($isEditing)
                <input type="hidden" name="mode" value="change">
                <input type="hidden" name="unit" value="consumption">
            @else
                <fieldset class="space-y-2">
                    <legend class="text-sm font-medium text-gray-700">How to adjust <span class="text-red-500">*</span></legend>
                    <div class="flex flex-wrap gap-4 pt-0.5">
                        <label class="inline-flex items-center gap-2.5 h-11 text-sm font-medium text-gray-800 cursor-pointer pr-2">
                            <input type="radio" name="mode" value="change" x-model="mode" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            Change stock
                        </label>
                        <label class="inline-flex items-center gap-2.5 h-11 text-sm font-medium text-gray-800 cursor-pointer pr-2">
                            <input type="radio" name="mode" value="exact" x-model="mode" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            Set exact quantity
                        </label>
                    </div>
                    <p class="text-xs text-gray-500" x-text="mode === 'change' ? 'Enter how much to add (positive) or remove (negative).' : 'Enter the final on-hand quantity after this adjustment.'"></p>
                    @error('mode')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </fieldset>

                <div x-show="adjustable === 'ingredient' && hasDualUnits()" x-cloak class="space-y-2">
                    <span class="block text-sm font-medium text-gray-700">Unit <span class="text-red-500">*</span></span>
                    <div class="flex flex-wrap gap-4 pt-0.5">
                        <label class="inline-flex items-center gap-2.5 h-11 text-sm font-medium text-gray-800 cursor-pointer pr-2">
                            <input type="radio" value="consumption" x-model="unit" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span x-text="unitLabel('consumption')"></span>
                            <span class="text-xs text-gray-500 font-normal">(consumption)</span>
                        </label>
                        <label class="inline-flex items-center gap-2.5 h-11 text-sm font-medium text-gray-800 cursor-pointer pr-2">
                            <input type="radio" value="purchase" x-model="unit" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span x-text="unitLabel('purchase')"></span>
                            <span class="text-xs text-gray-500 font-normal">(purchase)</span>
                        </label>
                    </div>
                    @error('unit')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <input type="hidden" name="unit" :value="(adjustable === 'ingredient' && hasDualUnits() && unit === 'purchase') ? 'purchase' : 'consumption'">
            @endif

            <div>
                <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">
                    <span x-text="mode === 'change' ? 'Quantity change' : 'Exact quantity'"></span>
                    <span class="text-red-500">*</span>
                    <span class="font-normal text-gray-500" x-text="'(' + activeUnitLabel() + ')'"></span>
                </label>
                <input type="number"
                       name="quantity"
                       id="quantity"
                       step="0.01"
                       required
                       x-model="quantity"
                       :min="mode === 'exact' ? '0' : null"
                       class="block w-full filter-control text-gray-900"
                       :placeholder="mode === 'change' ? 'e.g. 3 or -1' : 'e.g. 10'">
                <p class="mt-1 text-xs text-gray-500" x-show="mode === 'change'">Positive adds stock; negative removes stock.</p>
                <p class="mt-1 text-xs text-gray-500" x-show="mode === 'exact' && currentInActiveUnit() !== null">
                    {{ $isEditing ? 'Before this adjustment' : 'Current' }} in this unit:
                    <span class="font-medium text-gray-700" x-text="formatQty(currentInActiveUnit()) + ' ' + activeUnitLabel()"></span>
                </p>
                @error('quantity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div x-show="stock && previewDelta() !== null && Number.isFinite(previewDelta())" x-cloak
                 class="rounded-lg border border-indigo-100 bg-indigo-50/60 px-4 py-3 space-y-1">
                <p class="text-xs font-medium uppercase tracking-wide text-indigo-700">Preview</p>
                <p class="text-sm text-gray-800">
                    Change:
                    <span class="font-semibold" x-text="(previewDelta() >= 0 ? '+' : '') + formatWithUnit(previewDelta())"></span>
                </p>
                <p class="text-sm text-gray-800">
                    New stock:
                    <span class="font-semibold" :class="previewNewStock() < -0.0001 ? 'text-red-700' : 'text-gray-900'" x-text="formatWithUnit(previewNewStock())"></span>
                </p>
            </div>

            <div>
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes <span class="text-red-500">*</span></label>
                <textarea name="notes" id="notes" rows="3" required class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Reason for this adjustment">{{ $defaultNotes }}</textarea>
                @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <p x-show="submitError" x-cloak class="text-sm text-red-600" x-text="submitError"></p>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ $isEditing ? route('inventory.adjustment.show', $editingMovement) : route('inventory.adjustment.index') }}" class="inline-flex items-center justify-center h-11 px-4 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="inline-flex items-center justify-center h-11 px-4 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    {{ $isEditing ? 'Update adjustment' : 'Save adjustment' }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
