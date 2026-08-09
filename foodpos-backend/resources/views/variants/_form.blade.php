@php
    $isEdit = isset($variant) && $variant->exists;
    $formAction = $isEdit ? route('variants.update', $variant) : route('variants.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $variantData = $isEdit ? $variant->toArray() : [];
    // Prefill with old input when validation fails
    $variantData = [
        'name' => old('name', $variantData['name'] ?? ''),
        'code' => old('code', $isEdit ? ($variantData['code'] ?? '') : ($suggestedCode ?? '')),
        'description' => old('description', $variantData['description'] ?? ''),
        'options' => old('options', $variantData['options'] ?? []),
        'sort_order' => old('sort_order', $variantData['sort_order'] ?? 0),
        'is_active' => filter_var(old('is_active', $variantData['is_active'] ?? true), FILTER_VALIDATE_BOOLEAN),
    ];
    $title = $isEdit ? 'Edit Variant' : 'Create New Variant';
    $subtitle = $isEdit ? 'Update variant information' : 'Add a new variant (e.g., Size with Small, Medium, Large). Set default prices to prefill menu items.';
    $buttonText = $isEdit ? 'Update Variant' : 'Create Variant';
    $currencySymbol = currency_symbol();
@endphp

<div class="max-w-4xl mx-auto" x-data="variantForm({{ json_encode($variantData) }}, {{ $isEdit ? 'true' : 'false' }})">
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
                <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Variant Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Name -->
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
                               placeholder="Small, Medium, Large...">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Code -->
                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-700 mb-2">
                            Code
                        </label>
                        <input type="text" 
                               name="code" 
                               id="code" 
                               x-model="formData.code"
                               maxlength="50"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono @error('code') border-red-500 @enderror"
                               placeholder="V01, V02…">
                        <p class="mt-1 text-xs text-gray-500">Leave blank to auto-assign the next code (V01, V02…). You can enter your own code instead.</p>
                        @error('code')
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
                              x-model="formData.description"
                              rows="3"
                              class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('description') border-red-500 @enderror"
                              placeholder="Optional description for this variant"></textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Variant Options -->
                <div class="rounded-xl border border-gray-200 overflow-hidden bg-white shadow-sm">
                    <div class="px-5 py-4 border-b border-gray-200 bg-gradient-to-r from-slate-50 to-indigo-50/40">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">Options & default prices</h3>
                                <p class="mt-0.5 text-xs text-gray-500">Each row is one choice (Small, Medium, Large). Default prices prefill menu items — you can override per item later.</p>
                            </div>
                            <button type="button"
                                    @click="addOption()"
                                    class="inline-flex items-center justify-center h-10 px-4 text-sm font-medium text-indigo-700 bg-white border border-indigo-200 rounded-lg hover:bg-indigo-50 hover:border-indigo-300 transition-colors shrink-0">
                                <i class="fas fa-plus mr-2 text-xs"></i>Add option
                            </button>
                        </div>
                    </div>

                    <div x-show="formData.options.length > 0" class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-200">
                                    <th class="w-10 px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">#</th>
                                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 min-w-[160px]">Option name <span class="text-red-500 normal-case">*</span></th>
                                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-28">Code</th>
                                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-36">Default price</th>
                                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-20">Order</th>
                                    <th class="w-14 px-3 py-3"><span class="sr-only">Remove</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <template x-for="(option, index) in formData.options" :key="index">
                                    <tr class="group hover:bg-indigo-50/30 transition-colors">
                                        <td class="px-3 py-3 align-middle">
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600 group-hover:bg-indigo-100 group-hover:text-indigo-700"
                                                  x-text="index + 1"></span>
                                        </td>
                                        <td class="px-3 py-2 align-middle">
                                            <input type="text"
                                                   :name="'options[' + index + '][name]'"
                                                   :id="'option_name_' + index"
                                                   x-model="option.name"
                                                   required
                                                   class="block w-full h-10 px-3 rounded-lg border-gray-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-gray-900 placeholder:text-gray-400"
                                                   placeholder="e.g. Small">
                                        </td>
                                        <td class="px-3 py-2 align-middle">
                                            <input type="text"
                                                   :name="'options[' + index + '][code]'"
                                                   :id="'option_code_' + index"
                                                   x-model="option.code"
                                                   maxlength="20"
                                                   class="block w-full h-10 px-3 rounded-lg border-gray-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm placeholder:text-gray-400"
                                                   placeholder="O01">
                                        </td>
                                        <td class="px-3 py-2 align-middle">
                                            <div class="relative">
                                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 text-xs font-medium">{{ $currencySymbol }}</span>
                                                <input type="number"
                                                       :name="'options[' + index + '][price]'"
                                                       :id="'option_price_' + index"
                                                       x-model.number="option.price"
                                                       step="0.01"
                                                       min="0"
                                                       class="block w-full h-10 pl-9 pr-3 rounded-lg border-gray-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 tabular-nums"
                                                       placeholder="0.00">
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 align-middle">
                                            <input type="number"
                                                   :name="'options[' + index + '][sort_order]'"
                                                   :id="'option_sort_' + index"
                                                   x-model.number="option.sort_order"
                                                   min="0"
                                                   class="block w-full h-10 px-3 rounded-lg border-gray-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-center tabular-nums"
                                                   placeholder="0">
                                        </td>
                                        <td class="px-3 py-2 align-middle text-center">
                                            <button type="button"
                                                    @click="removeOption(index)"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 border border-transparent hover:border-red-100 transition-colors"
                                                    title="Remove option">
                                                <i class="fas fa-trash-alt text-sm"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div x-show="formData.options.length === 0"
                         x-cloak
                         class="px-6 py-12 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 mb-4">
                            <i class="fas fa-layer-group text-xl"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-900">No options yet</p>
                        <p class="mt-1 text-sm text-gray-500 max-w-sm mx-auto">Add options like Small, Medium, and Large with default prices for faster menu setup.</p>
                        <button type="button"
                                @click="addOption()"
                                class="mt-4 inline-flex items-center h-10 px-4 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">
                            <i class="fas fa-plus mr-2 text-xs"></i>Add first option
                        </button>
                    </div>

                    <div x-show="formData.options.length > 0" class="px-5 py-3 border-t border-gray-100 bg-gray-50/50 text-xs text-gray-500">
                        <span x-text="formData.options.length + ' option' + (formData.options.length === 1 ? '' : 's')"></span>
                        · Codes auto-assign (O01, O02…) when left blank
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
                               x-model.number="formData.sort_order"
                               min="0"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('sort_order') border-red-500 @enderror"
                               placeholder="0">
                        <p class="mt-1 text-xs text-gray-500">Lower numbers appear first</p>
                        @error('sort_order')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Is Active -->
                    <div class="flex items-end">
                        <label class="flex items-center">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" 
                                   name="is_active" 
                                   value="1"
                                   x-model="formData.is_active"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-700">Active</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('variants.index') }}"
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
function variantForm(variantData = null, isEdit = false) {
    return {
        formData: {
            name: variantData?.name || '',
            code: variantData?.code || '',
            description: variantData?.description || '',
            options: variantData?.options && Array.isArray(variantData.options) ? variantData.options.map(opt => ({
                name: opt.name || '',
                code: opt.code || '',
                sort_order: opt.sort_order ?? 0,
                price: opt.price != null && opt.price !== '' ? Number(opt.price) : '',
            })) : [],
            sort_order: variantData?.sort_order ?? 0,
            is_active: variantData?.is_active ?? true,
        },
        addOption() {
            this.formData.options.push({
                name: '',
                code: '',
                sort_order: this.formData.options.length,
                price: '',
            });
        },
        removeOption(index) {
            this.formData.options.splice(index, 1);
        },
        submitForm(event) {
            event.target.submit();
        }
    }
}
</script>
