@php
    $isEdit = isset($ingredientUnit) && $ingredientUnit->exists;
    $formAction = $isEdit ? route('ingredient-units.update', $ingredientUnit) : route('ingredient-units.store');
    $unitData = $isEdit ? $ingredientUnit->toArray() : [];
    $unitData['code'] = old('code', $isEdit ? ($ingredientUnit->code ?? '') : ($suggestedCode ?? ''));
    $title = $isEdit ? 'Edit Ingredient Unit' : 'Create Ingredient Unit';
    $subtitle = $isEdit ? 'Update unit information' : 'Add a new unit for your ingredients';
    $buttonText = $isEdit ? 'Update Unit' : 'Create Unit';
@endphp

<div class="max-w-2xl mx-auto" x-data="ingredientUnitForm({{ json_encode($unitData) }})">
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
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                               placeholder="Kilogram, Liter, Piece…">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-700 mb-2">
                            Code
                        </label>
                        <input type="text"
                               name="code"
                               id="code"
                               x-model="formData.code"
                               maxlength="20"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono @error('code') border-red-500 @enderror"
                               placeholder="C01, C02…">
                        <p class="mt-1 text-xs text-gray-500">Leave blank to auto-assign the next code (C01, C02…). You can enter your own code instead.</p>
                        @error('code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description
                    </label>
                    <textarea name="description"
                              id="description"
                              rows="3"
                              x-model="formData.description"
                              class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('description') border-red-500 @enderror"
                              placeholder="Optional notes about this unit…"></textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('ingredient-units.index') }}"
                   class="px-4 py-2 h-12 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </a>
                <button type="submit"
                        class="px-4 py-2 h-12 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <i class="fas fa-save mr-2"></i>
                    {{ $buttonText }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function ingredientUnitForm(unitData = null) {
    return {
        formData: {
            name: unitData?.name || '',
            code: unitData?.code || '',
            description: unitData?.description || '',
        },

        submitForm(event) {
            event.target.submit();
        }
    }
}
</script>
