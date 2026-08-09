@php
    $isEdit = isset($tax) && $tax->exists;
    $formAction = $isEdit ? route('taxes.update', $tax) : route('taxes.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $taxData = $isEdit ? $tax->toArray() : [];
    $title = $isEdit ? 'Edit Tax' : 'Create New Tax';
    $subtitle = $isEdit ? 'Update tax information' : 'Add a new tax rate for your business';
    $buttonText = $isEdit ? 'Update Tax' : 'Create Tax';
@endphp

<div class="max-w-2xl mx-auto" x-data="taxForm({{ json_encode($taxData) }}, {{ $isEdit ? 'true' : 'false' }})">
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
                <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Tax Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Tax Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Tax Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="name" 
                               id="name" 
                               x-model="formData.name"
                               required
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror"
                               placeholder="VAT, Sales Tax, GST...">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Percentage -->
                    <div>
                        <label for="percentage" class="block text-sm font-medium text-gray-700 mb-2">
                            Percentage <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="number" 
                                   name="percentage" 
                                   id="percentage" 
                                   step="0.01"
                                   min="0"
                                   max="100"
                                   x-model="formData.percentage"
                                   required
                                   class="block w-full h-12 px-4 pr-10 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('percentage') border-red-500 @enderror"
                                   placeholder="10.50">
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <span class="text-gray-500 text-sm">%</span>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Enter the tax percentage (e.g., 10.5 for 10.5%)</p>
                        @error('percentage')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Status -->
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" 
                               name="is_active" 
                               value="1"
                               x-model="formData.is_active"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-600">Active (Tax will be applied to transactions)</span>
                    </label>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('taxes.index') }}"
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
function taxForm(taxData = null, isEdit = false) {
    return {
        formData: {
            name: taxData?.name || '',
            percentage: taxData?.percentage ?? 0,
            is_active: taxData?.is_active ?? true,
        },

        submitForm(event) {
            // Let the form submit normally
            event.target.submit();
        }
    }
}
</script>

