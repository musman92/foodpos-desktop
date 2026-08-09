@php
    $isEdit = isset($ingredientCategory) && $ingredientCategory->exists;
    $routePrefix = $routePrefix ?? 'ingredient-categories';
    $formAction = $isEdit ? route($routePrefix.'.update', $ingredientCategory) : route($routePrefix.'.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $categoryData = $isEdit ? $ingredientCategory->toArray() : [];
    $categoryData['code'] = old('code', $isEdit ? ($ingredientCategory->code ?? '') : ($suggestedCode ?? ''));
    $title = $isEdit ? 'Edit Ingredient Category' : 'Create New Ingredient Category';
    $subtitle = $isEdit ? 'Update ingredient category information' : 'Add a new category to organize your ingredients';
    $buttonText = $isEdit ? 'Update Category' : 'Create Category';
    $cancelRoute = $routePrefix.'.index';
@endphp

<div class="max-w-2xl mx-auto" x-data="ingredientCategoryForm({{ json_encode($categoryData) }}, {{ $isEdit ? 'true' : 'false' }})">
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

            <!-- Basic Information -->
            <div class="space-y-6">
                <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Category Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Category Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Category Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="name" 
                               id="name" 
                               x-model="formData.name"
                               required
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror"
                               placeholder="Dry Goods, Fresh Produce, Spices...">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Category Code -->
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

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Sort Order -->
                    <div>
                        <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-2">
                            Sort Order
                        </label>
                        <input type="number" 
                               name="sort_order" 
                               id="sort_order" 
                               min="0"
                               x-model="formData.sort_order"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('sort_order') border-red-500 @enderror"
                               placeholder="0">
                        <p class="mt-1 text-xs text-gray-500">Lower numbers appear first</p>
                        @error('sort_order')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description
                    </label>
                    <textarea name="description" 
                              id="description" 
                              rows="3"
                              x-model="formData.description"
                              class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('description') border-red-500 @enderror"
                              placeholder="Brief description of this category..."></textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Status -->
            <div class="space-y-6 pt-6 border-t border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Status</h2>
                
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" 
                               name="is_active" 
                               value="1"
                               x-model="formData.is_active"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-600">Active (Category will be available for use)</span>
                    </label>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route($cancelRoute) }}"
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
function ingredientCategoryForm(categoryData = null, isEdit = false) {
    return {
        formData: {
            name: categoryData?.name || '',
            code: categoryData?.code || '',
            description: categoryData?.description || '',
            sort_order: categoryData?.sort_order ?? 0,
            is_active: categoryData?.is_active ?? true,
        },

        submitForm(event) {
            // Let the form submit normally
            event.target.submit();
        }
    }
}
</script>

