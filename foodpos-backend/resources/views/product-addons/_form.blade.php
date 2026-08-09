@php
    $isEdit = isset($productAddon) && $productAddon->exists;
    $formAction = $isEdit ? route('product-addons.update', $productAddon) : route('product-addons.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $productAddonData = $isEdit ? $productAddon->toArray() : [];
    $recipesData = $isEdit ? $productAddon->recipes->map(fn ($r) => [
        'ingredient_id' => $r->ingredient_id,
        'quantity' => $r->quantity,
        'unit_id' => $r->unit_id,
        'waste_percentage' => $r->waste_percentage,
        'notes' => $r->notes,
    ])->values()->all() : [];
    $title = $isEdit ? 'Edit Product Addon' : 'Create New Product Addon';
    $subtitle = $isEdit ? 'Update product addon information' : 'Add a new product addon for your menu items';
    $buttonText = $isEdit ? 'Update Product Addon' : 'Create Product Addon';
@endphp

<div class="max-w-4xl mx-auto" x-data="productAddonForm(
    {{ json_encode($productAddonData) }},
    {{ json_encode($recipesData) }},
    {{ $isEdit ? 'true' : 'false' }},
    {{ json_encode($ingredients ?? []) }},
    {{ json_encode(($singleMenuItems ?? collect())->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'sku' => $m->sku, 'cost' => (float) $m->cost])->values()) }},
    {{ json_encode($suggestedCode ?? '') }},
    {{ json_encode($currency ?? 'USD') }}
)">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-xl font-semibold text-gray-900">{{ $title }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
        </div>

        <form action="{{ $formAction }}" method="POST" class="p-6 space-y-6" @submit.prevent="submitForm">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="space-y-6">
                <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Basic Information</h2>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-700 mb-2">Code</label>
                        <input type="text"
                               name="code"
                               id="code"
                               x-model="formData.code"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('code') border-red-500 @enderror"
                               placeholder="{{ $suggestedCode ?? 'PA01' }}">
                        <p class="mt-1 text-xs text-gray-500">Leave blank to auto-assign (e.g. PA01)</p>
                        @error('code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               name="name"
                               id="name"
                               x-model="formData.name"
                               required
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror"
                               placeholder="Extra Cheese, Large Size...">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700 mb-2">
                            Sale Price <span class="text-red-500">*</span>
                        </label>
                        <input type="number"
                               name="price"
                               id="price"
                               step="0.01"
                               min="0"
                               x-model="formData.price"
                               required
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('price') border-red-500 @enderror"
                               placeholder="0.00">
                        @error('price')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="rounded-lg border border-indigo-100 bg-indigo-50/60 px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-indigo-900">Estimated cost price</p>
                        <p class="text-xs text-indigo-700">From linked menu item or recipe ingredients (incl. waste).</p>
                    </div>
                    <p class="text-xl font-bold text-indigo-900 tabular-nums" x-text="formatMoney(calculatedCost)"></p>
                </div>
            </div>

            <div class="space-y-6">
                <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Inventory</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700 mb-2">
                            Inventory Type <span class="text-red-500">*</span>
                        </label>
                        <select name="type"
                                id="type"
                                x-model="formData.type"
                                @change="handleTypeChange()"
                                required
                                class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="none">None — no stock deduction</option>
                            <option value="recipe">Recipe — deduct ingredients</option>
                            <option value="single">Single item — deduct menu item stock</option>
                        </select>
                        @error('type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-end">
                        <label class="flex items-center h-12">
                            <input type="hidden" name="track_inventory" value="0">
                            <input type="checkbox"
                                   name="track_inventory"
                                   value="1"
                                   x-model="formData.track_inventory"
                                   :disabled="formData.type === 'none'"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50">
                            <span class="ml-2 text-sm text-gray-600">Auto-deduct stock when ordered</span>
                        </label>
                    </div>
                </div>

                <div x-show="formData.type === 'single'" x-cloak>
                    <label for="menu_item_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Linked menu item <span class="text-red-500">*</span>
                    </label>
                    <select name="menu_item_id"
                            id="menu_item_id"
                            x-model="formData.menu_item_id"
                            class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select menu item...</option>
                        @foreach($singleMenuItems ?? [] as $menuItem)
                            <option value="{{ $menuItem->id }}" {{ ($isEdit && $productAddon->menu_item_id == $menuItem->id) ? 'selected' : '' }}>
                                {{ $menuItem->name }}@if($menuItem->sku) ({{ $menuItem->sku }})@endif
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Uses branch stock of this single-type menu item when addon is sold.</p>
                    @error('menu_item_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div x-show="formData.type === 'recipe'" x-cloak class="space-y-6">
                <div class="border-b border-gray-200 pb-2">
                    <h2 class="text-lg font-semibold text-gray-900">Recipe Ingredients</h2>
                    <p class="mt-1 text-sm text-gray-500">Define what this addon consumes per serving.</p>
                </div>

                <div x-data="recipeCatalogSelect($data)" x-init="init()">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Add ingredient</label>
                    <x-searchable-select />
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase w-10">SN</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase min-w-[160px]">Ingredient</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase w-40">Consumption</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase w-28">Cost</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase w-28">Total</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase w-24">Waste %</th>
                                <th class="px-3 py-3 w-14"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="({ recipe, index }, rowIdx) in recipeRows" :key="'addon-recipe-' + index">
                                <tr class="hover:bg-gray-50 align-top">
                                    <td class="px-3 py-3 text-gray-500" x-text="rowIdx + 1"></td>
                                    <td class="px-3 py-3">
                                        <input type="hidden" :name="'recipes[' + index + '][ingredient_id]'" :value="recipe.ingredient_id">
                                        <input type="hidden" :name="'recipes[' + index + '][unit_id]'" :value="recipe.unit_id || ''">
                                        <p class="font-medium text-gray-900" x-text="ingredientLabel(recipe.ingredient_id)"></p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-2">
                                            <input type="number"
                                                   :name="'recipes[' + index + '][quantity]'"
                                                   x-model.number="recipe.quantity"
                                                   step="0.0001"
                                                   min="0.0001"
                                                   required
                                                   class="w-24 h-9 px-2 rounded border-gray-300 text-sm">
                                            <span class="text-xs text-gray-500" x-text="unitLabel(recipe.unit_id, recipe.ingredient_id)"></span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-right text-gray-600 tabular-nums" x-text="formatMoney(ingredientCost(recipe.ingredient_id))"></td>
                                    <td class="px-3 py-3 text-right font-medium text-gray-900 tabular-nums" x-text="formatMoney(recipeLineTotal(recipe))"></td>
                                    <td class="px-3 py-3 text-right">
                                        <input type="number"
                                               :name="'recipes[' + index + '][waste_percentage]'"
                                               x-model.number="recipe.waste_percentage"
                                               step="0.01"
                                               min="0"
                                               class="w-20 h-9 px-2 rounded border-gray-300 text-sm text-right">
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <button type="button" @click="removeRecipe(index)" class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="recipeRows.length === 0">
                                <td colspan="7" class="px-3 py-8 text-center text-sm text-gray-500">No ingredients yet. Search and add above.</td>
                            </tr>
                        </tbody>
                        <tfoot x-show="recipeRows.length > 0" class="bg-gray-50">
                            <tr>
                                <td colspan="4" class="px-3 py-3 text-right text-sm font-medium text-gray-700">Recipe cost total</td>
                                <td class="px-3 py-3 text-right text-sm font-bold text-indigo-700 tabular-nums" x-text="formatMoney(recipeTotalCost)"></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('product-addons.index') }}"
                   class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2 h-12 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                    <i class="fas fa-save mr-2"></i>
                    {{ $buttonText }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function productAddonForm(productAddonData = null, recipesData = [], isEdit = false, ingredients = [], singleMenuItems = [], suggestedCode = '', currency = 'USD') {
    return {
        formData: {
            code: productAddonData?.code || suggestedCode || '',
            name: productAddonData?.name || '',
            price: productAddonData?.price ?? 0,
            type: productAddonData?.type || 'none',
            track_inventory: productAddonData?.track_inventory ?? false,
            menu_item_id: productAddonData?.menu_item_id ? String(productAddonData.menu_item_id) : '',
        },
        recipes: (recipesData || []).map(recipe => ({
            ingredient_id: recipe.ingredient_id ? String(recipe.ingredient_id) : '',
            quantity: recipe.quantity || 0,
            unit_id: recipe.unit_id ? String(recipe.unit_id) : '',
            waste_percentage: recipe.waste_percentage || 0,
            notes: recipe.notes || '',
        })),
        ingredients: Array.isArray(ingredients) ? ingredients : [],
        singleMenuItems: Array.isArray(singleMenuItems) ? singleMenuItems : [],
        units: units || {},
        currency: currency || 'USD',
        recipePicker: null,

        init() {
            this.recipes.forEach((_, index) => this.syncRecipeUnit(index));
            if (this.formData.type !== 'none' && !isEdit) {
                this.formData.track_inventory = true;
            }
        },

        get recipeRows() {
            return this.recipes.map((recipe, index) => ({ recipe, index }));
        },

        get recipeTotalCost() {
            return this.recipes.reduce((sum, recipe) => sum + this.recipeLineTotal(recipe), 0);
        },

        get calculatedCost() {
            if (this.formData.type === 'recipe') {
                return this.recipeTotalCost;
            }
            if (this.formData.type === 'single' && this.formData.menu_item_id) {
                const item = this.singleMenuItems.find(m => String(m.id) === String(this.formData.menu_item_id));
                return item ? Number(item.cost) || 0 : 0;
            }
            return Number(productAddonData?.cost) || 0;
        },

        handleTypeChange() {
            if (this.formData.type === 'none') {
                this.formData.track_inventory = false;
                this.formData.menu_item_id = '';
            } else if (!this.formData.track_inventory) {
                this.formData.track_inventory = true;
            }
        },

        ingredientEntry(id) {
            return this.ingredients.find(i => String(i.id) === String(id));
        },

        ingredientLabel(id) {
            const entry = this.ingredientEntry(id);
            return entry ? entry.label || entry.name : 'Ingredient';
        },

        ingredientCost(id) {
            const entry = this.ingredientEntry(id);
            return entry ? Number(entry.cost_per_unit) || 0 : 0;
        },

        recipeLineTotal(recipe) {
            const qty = Number(recipe.quantity) || 0;
            const waste = Number(recipe.waste_percentage) || 0;
            return qty * (1 + waste / 100) * this.ingredientCost(recipe.ingredient_id);
        },

        unitLabel(unitId, ingredientId) {
            const entry = this.ingredientEntry(ingredientId);
            if (unitId) {
                const match = (this.ingredients || []).find(i => String(i.consumption_unit_key) === String(unitId) || String(i.purchase_unit_key) === String(unitId));
                if (match) {
                    if (String(match.consumption_unit_key) === String(unitId)) {
                        return match.consumption_unit_name || '—';
                    }
                    return match.purchase_unit_name || '—';
                }
            }

            return entry?.consumption_unit_name || '—';
        },

        syncRecipeUnit(index) {
            const recipe = this.recipes[index];
            if (!recipe || recipe.unit_id) {
                return;
            }
            const entry = this.ingredientEntry(recipe.ingredient_id);
            if (entry) {
                recipe.unit_id = String(entry.consumption_unit_key || entry.consumption_unit_id || '');
            }
        },

        addRecipeFromIngredient(ingredientId) {
            const entry = this.ingredientEntry(ingredientId);
            if (!entry) {
                return;
            }

            const duplicate = this.recipes.some(r => String(r.ingredient_id) === String(ingredientId));
            if (duplicate) {
                alert('This ingredient is already in the recipe.');
                this.resetRecipePicker();
                return;
            }

            this.recipes.push({
                ingredient_id: String(ingredientId),
                quantity: 1,
                unit_id: String(entry.consumption_unit_key || entry.consumption_unit_id || ''),
                waste_percentage: 0,
                notes: '',
            });

            this.resetRecipePicker();
        },

        resetRecipePicker() {
            if (!this.recipePicker) {
                return;
            }
            this.recipePicker.selectedValue = '';
            this.recipePicker.searchQuery = '';
            this.recipePicker.isOpen = false;
            this.recipePicker.highlightedIndex = -1;
        },

        removeRecipe(index) {
            this.recipes.splice(index, 1);
        },

        formatMoney(amount) {
            const n = Number(amount) || 0;
            try {
                return new Intl.NumberFormat(undefined, { style: 'currency', currency: this.currency }).format(n);
            } catch (e) {
                return n.toFixed(2);
            }
        },

        submitForm(event) {
            event.target.submit();
        },
    };
}
</script>
