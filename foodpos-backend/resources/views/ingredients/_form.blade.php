@php
    $isEdit = isset($ingredient) && $ingredient->exists;
    $routePrefix = $routePrefix ?? 'ingredients';
    $formAction = $isEdit ? route($routePrefix.'.update', $ingredient) : route($routePrefix.'.store');
    $ingredientData = $isEdit ? $ingredient->toArray() : [];
    $ingredientData['sku'] = old('sku', $isEdit ? ($ingredient->sku ?? '') : ($suggestedSku ?? ''));
    $title = $isEdit ? 'Edit Ingredient' : 'Create Ingredient';
    $subtitle = $isEdit ? 'Update ingredient information' : 'Set purchase and consumption units once — purchases use these automatically';
    $buttonText = $isEdit ? 'Update Ingredient' : 'Create Ingredient';
    $cancelRoute = $routePrefix.'.index';
    $categoryOptions = ($categories ?? collect())->map(fn ($category) => [
        'id' => (int) $category->id,
        'name' => $category->name,
        'code' => $category->code,
        'label' => $category->displayLabel(),
    ])->values();
    $unitOptions = ($units ?? collect())->map(fn ($unit) => [
        'id' => (int) $unit->id,
        'name' => $unit->name,
        'code' => $unit->code,
        'label' => $unit->displayLabel(),
    ])->values();
@endphp

<div class="max-w-5xl mx-auto" x-data="ingredientForm({{ json_encode($ingredientData) }}, {{ json_encode($categoryOptions) }}, {{ json_encode($unitOptions) }})">
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

            <div class="rounded-lg border border-indigo-100 bg-indigo-50/60 px-4 py-3 text-sm text-indigo-900">
                <p class="font-medium">How conversion works</p>
                <p class="mt-1 text-indigo-800">
                    Tell us how many <strong>consumption units</strong> are inside <strong>1 purchase unit</strong>.
                    Example: buy oil in <strong>1 LTR</strong>, use in <strong>ml</strong>, conversion <strong>1000</strong>.
                    Or buy in <strong>1 LTR</strong>, use in a custom <strong>50 ml</strong> unit, conversion <strong>20</strong> — recipe qty 1 = 50 ml.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
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
                           placeholder="e.g. Cooking Oil">
                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="sku" class="block text-sm font-medium text-gray-700 mb-2">Code</label>
                    <input type="text"
                           name="sku"
                           id="sku"
                           x-model="formData.sku"
                           maxlength="50"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono @error('sku') border-red-500 @enderror"
                           placeholder="101, 102…">
                    <p class="mt-1 text-xs text-gray-500">Leave blank to auto-assign the next code. You can enter your own code instead.</p>
                    @error('sku')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div x-data="categorySelect($data)" x-init="init()">
                    <x-searchable-select
                        label="Category"
                        required
                        id="category_id"
                    >
                        <x-slot:hiddenInput>
                            <input type="hidden" name="category_id" x-model="selectedValue" required>
                        </x-slot:hiddenInput>
                    </x-searchable-select>
                    <p class="mt-1 text-xs text-gray-500">Search by code or name — e.g. Bread, Oils, Spices</p>
                    @error('category_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div x-data="ingredientUnitSelect($data, 'purchase_unit_id')" x-init="init()">
                    <x-searchable-select
                        label="Purchase unit"
                        required
                        id="purchase_unit_id"
                    >
                        <x-slot:hiddenInput>
                            <input type="hidden" name="purchase_unit_id" x-model="selectedValue" required>
                        </x-slot:hiddenInput>
                    </x-searchable-select>
                    <p class="mt-1 text-xs text-gray-500">How you buy from supplier — e.g. 1 LTR, 20 KG box</p>
                    @error('purchase_unit_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div x-data="ingredientUnitSelect($data, 'consumption_unit_id')" x-init="init()">
                    <x-searchable-select
                        label="Consumption unit"
                        required
                        id="consumption_unit_id"
                    >
                        <x-slot:hiddenInput>
                            <input type="hidden" name="consumption_unit_id" x-model="selectedValue" required>
                        </x-slot:hiddenInput>
                    </x-searchable-select>
                    <p class="mt-1 text-xs text-gray-500">How recipes & stock count it — e.g. ml, gram, 50 ml</p>
                    @error('consumption_unit_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="conversion_rate" class="block text-sm font-medium text-gray-700 mb-2">
                        Conversion <span class="text-red-500">*</span>
                    </label>
                    <input type="number"
                           name="conversion_rate"
                           id="conversion_rate"
                           x-model="formData.conversion_rate"
                           @input="recalculateCost()"
                           step="0.0001"
                           min="0.0001"
                           required
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('conversion_rate') border-red-500 @enderror"
                           placeholder="e.g. 1000">
                    <p class="mt-1 text-xs text-gray-500">Consumption units in 1 purchase unit</p>
                    @error('conversion_rate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label for="purchase_price" class="block text-sm font-medium text-gray-700 mb-2">
                        Purchase price <span class="text-red-500">*</span>
                    </label>
                    <input type="number"
                           name="purchase_price"
                           id="purchase_price"
                           x-model="formData.purchase_price"
                           @input="recalculateCost()"
                           step="0.01"
                           min="0"
                           required
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('purchase_price') border-red-500 @enderror"
                           placeholder="e.g. 5000">
                    <p class="mt-1 text-xs text-gray-500">Price for 1 purchase unit — used as default on purchases</p>
                    @error('purchase_price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Cost per unit</label>
                    <div class="h-12 px-4 flex items-center rounded-lg border border-gray-200 bg-gray-50 text-sm font-semibold text-gray-900">
                        <span x-text="displayCostPerUnit()"></span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Auto: purchase price ÷ conversion</p>
                </div>

                <div>
                    <label for="min_stock_level" class="block text-sm font-medium text-gray-700 mb-2">
                        Low qty <span class="text-red-500">*</span>
                    </label>
                    <input type="number"
                           name="min_stock_level"
                           id="min_stock_level"
                           x-model="formData.min_stock_level"
                           step="0.01"
                           min="0"
                           required
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('min_stock_level') border-red-500 @enderror"
                           placeholder="e.g. 500">
                    <p class="mt-1 text-xs text-gray-500">Alert when stock falls below this (in consumption units)</p>
                    @error('min_stock_level')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description"
                              id="description"
                              rows="2"
                              x-model="formData.description"
                              class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                              placeholder="Optional notes…"></textarea>
                </div>
                <div class="flex items-end pb-1">
                    <label class="flex items-center">
                        <input type="checkbox"
                               name="is_active"
                               value="1"
                               x-model="formData.is_active"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-600">Active (available for recipes and purchases)</span>
                    </label>
                </div>
            </div>

            <p class="text-xs text-gray-500">
                <a href="{{ route('ingredient-units.index') }}" class="text-indigo-600 hover:text-indigo-800">Manage ingredient units</a>
            </p>

            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route($cancelRoute) }}"
                   class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Back
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
function ingredientForm(ingredientData = null, categoryOptions = [], unitOptions = []) {
    return {
        formData: {
            name: ingredientData?.name || '',
            sku: ingredientData?.sku || '',
            category_id: ingredientData?.category_id ? String(ingredientData.category_id) : '',
            purchase_unit_id: ingredientData?.purchase_unit_id ? String(ingredientData.purchase_unit_id) : '',
            consumption_unit_id: ingredientData?.consumption_unit_id ? String(ingredientData.consumption_unit_id) : '',
            conversion_rate: ingredientData?.conversion_rate ?? '',
            purchase_price: ingredientData?.purchase_price ?? '',
            min_stock_level: ingredientData?.min_stock_level ?? 0,
            description: ingredientData?.description || '',
            is_active: ingredientData?.is_active ?? true,
        },
        categoryOptions: Array.isArray(categoryOptions) ? categoryOptions : [],
        unitOptions: Array.isArray(unitOptions) ? unitOptions : [],

        recalculateCost() {},

        displayCostPerUnit() {
            const price = parseFloat(this.formData.purchase_price) || 0;
            const rate = parseFloat(this.formData.conversion_rate) || 0;
            if (rate <= 0) {
                return '0.00';
            }
            return (price / rate).toFixed(4);
        },

        submitForm(event) {
            event.target.submit();
        }
    }
}
</script>
