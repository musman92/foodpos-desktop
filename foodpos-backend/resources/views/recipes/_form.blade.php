@php
    $isEdit = isset($recipe) && $recipe->exists;
    $formAction = $isEdit ? route('recipes.update', $recipe) : route('recipes.store');
    $recipeData = [
        'name' => old('name', $isEdit ? $recipe->name : ''),
        'code' => old('code', $isEdit ? ($recipe->code ?? '') : ($suggestedCode ?? '')),
        'description' => old('description', $isEdit ? ($recipe->description ?? '') : ''),
        'is_active' => filter_var(old('is_active', $isEdit ? ($recipe->is_active ?? true) : true), FILTER_VALIDATE_BOOLEAN),
    ];
    $oldItems = old('items');
    if (is_array($oldItems)) {
        $itemsData = collect($oldItems)->map(fn ($r) => [
            'ingredient_id' => isset($r['ingredient_id']) ? (string) $r['ingredient_id'] : '',
            'quantity' => $r['quantity'] ?? 1,
            'unit_id' => isset($r['unit_id']) ? (string) $r['unit_id'] : '',
            'waste_percentage' => $r['waste_percentage'] ?? 0,
            'notes' => $r['notes'] ?? '',
        ])->values()->all();
    } elseif ($isEdit) {
        $itemsData = $recipe->items->map(fn ($r) => [
            'ingredient_id' => (string) $r->ingredient_id,
            'quantity' => $r->quantity,
            'unit_id' => $r->unit_id !== null ? (string) $r->unit_id : '',
            'waste_percentage' => $r->waste_percentage ?? 0,
            'notes' => $r->notes ?? '',
        ])->values()->all();
    } else {
        $itemsData = [];
    }
    $title = $isEdit ? 'Edit Recipe' : 'Create Recipe';
    $subtitle = $isEdit ? 'Update ingredients and quantities' : 'Build a reusable ingredient list for menu items';
    $buttonText = $isEdit ? 'Update Recipe' : 'Create Recipe';
    $currency = $currency ?? (get_company_config()['currency'] ?? 'USD');
@endphp

<div class="max-w-4xl mx-auto" x-data="catalogRecipeForm({{ json_encode($recipeData) }}, {{ json_encode($itemsData) }}, {{ json_encode($ingredients ?? []) }}, {{ json_encode($currency) }})">
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
                <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Recipe details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="name" x-model="formData.name" required
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="Burger — Large">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-700 mb-2">Code</label>
                        <input type="text" name="code" id="code" x-model="formData.code" maxlength="50"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono"
                               placeholder="{{ $suggestedCode ?? 'R01' }}">
                        <p class="mt-1 text-xs text-gray-500">Leave blank to auto-assign (R01, R02…)</p>
                        @error('code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" id="description" x-model="formData.description" rows="2"
                              class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                </div>
                <label class="flex items-center">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" x-model="formData.is_active"
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-600">Active</span>
                </label>
            </div>

            <div class="space-y-4">
                <div class="flex items-center justify-between border-b border-gray-200 pb-2">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Ingredients</h2>
                        <p class="text-sm text-gray-500">At least one ingredient is required.</p>
                    </div>
                    <p class="text-sm font-semibold text-indigo-700 tabular-nums" x-text="'Est. cost: ' + formatCurrency(totalCost)"></p>
                </div>

                <div x-data="recipeCatalogSelect($data)" x-init="init()">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Add ingredient</label>
                    <x-searchable-select />
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="listing-table min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ingredient</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cost</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Waste %</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <template x-for="(item, index) in items" :key="'ri-' + index + '-' + item.ingredient_id">
                                <tr>
                                    <td class="px-3 py-3">
                                        <input type="hidden" :name="'items[' + index + '][ingredient_id]'" :value="item.ingredient_id">
                                        <input type="hidden" :name="'items[' + index + '][unit_id]'" :value="item.unit_id || ''">
                                        <span class="font-medium text-gray-900" x-text="ingredientLabel(item.ingredient_id)"></span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-2">
                                            <input type="number" step="0.01" min="0.01" required
                                                   :name="'items[' + index + '][quantity]'"
                                                   x-model="item.quantity"
                                                   class="block w-24 h-10 px-2 rounded-lg border-gray-300 text-sm">
                                            <span class="text-gray-500 whitespace-nowrap" x-text="ingredientUnit(item.ingredient_id)"></span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-right tabular-nums" x-text="formatCurrency(lineTotal(item))"></td>
                                    <td class="px-3 py-3 text-right">
                                        <input type="number" step="0.01" min="0" max="100"
                                               :name="'items[' + index + '][waste_percentage]'"
                                               x-model="item.waste_percentage"
                                               class="block w-20 h-10 px-2 rounded-lg border-gray-300 text-sm text-right ml-auto">
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <button type="button" @click="removeItem(index)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="items.length === 0">
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">No ingredients yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @error('items')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-200">
                <a href="{{ route('recipes.index') }}" class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
                <button type="submit" class="px-4 py-2 h-12 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                    <i class="fas fa-save mr-2"></i>{{ $buttonText }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function catalogRecipeForm(recipeData, itemsData, ingredients, currency) {
    return {
        formData: {
            name: recipeData?.name || '',
            code: recipeData?.code || '',
            description: recipeData?.description || '',
            is_active: recipeData?.is_active ?? true,
        },
        items: Array.isArray(itemsData) ? itemsData.map(i => ({
            ingredient_id: i.ingredient_id ? String(i.ingredient_id) : '',
            quantity: i.quantity || 1,
            unit_id: i.unit_id ? String(i.unit_id) : '',
            waste_percentage: i.waste_percentage || 0,
            notes: i.notes || '',
        })) : [],
        ingredients: Array.isArray(ingredients) ? ingredients : [],
        currency: currency || 'USD',
        recipePicker: null,

        addRecipeFromIngredient(ingredientId) {
            const entry = this.ingredients.find(ing => String(ing.id) === String(ingredientId));
            if (!entry) return;
            if (this.items.some(i => String(i.ingredient_id) === String(ingredientId))) {
                alert('This ingredient is already in the recipe.');
                this.resetRecipePicker();
                return;
            }
            this.items.push({
                ingredient_id: String(ingredientId),
                quantity: 1,
                unit_id: String(entry.consumption_unit_id || entry.consumption_unit_key || entry.base_unit_id || ''),
                waste_percentage: 0,
                notes: '',
            });
            this.resetRecipePicker();
        },

        resetRecipePicker() {
            if (!this.recipePicker) return;
            this.recipePicker.selectedValue = '';
            this.recipePicker.searchQuery = '';
            this.recipePicker.isOpen = false;
            this.recipePicker.highlightedIndex = -1;
        },

        removeItem(index) {
            this.items.splice(index, 1);
        },

        ingredientLabel(id) {
            return this.ingredients.find(i => String(i.id) === String(id))?.name || '—';
        },

        ingredientUnit(id) {
            const e = this.ingredients.find(i => String(i.id) === String(id));
            return e?.consumption_unit_name || '';
        },

        ingredientCost(id) {
            return parseFloat(this.ingredients.find(i => String(i.id) === String(id))?.cost_per_unit) || 0;
        },

        lineTotal(item) {
            const qty = parseFloat(item.quantity) || 0;
            const waste = parseFloat(item.waste_percentage) || 0;
            return qty * (1 + waste / 100) * this.ingredientCost(item.ingredient_id);
        },

        get totalCost() {
            return this.items.reduce((s, i) => s + this.lineTotal(i), 0);
        },

        formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: this.currency,
                minimumFractionDigits: 2,
            }).format(parseFloat(amount) || 0);
        },

        submitForm(event) {
            if (this.items.length === 0) {
                alert('Add at least one ingredient.');
                return;
            }
            event.target.submit();
        },
    };
}
</script>
