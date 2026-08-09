@php
    $isEdit = isset($supplier) && $supplier->exists;
    $formAction = $isEdit ? route('suppliers.update', $supplier) : route('suppliers.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $supplierData = $isEdit ? $supplier->toArray() : [];
    $supplierData['code'] = old('code', $isEdit ? ($supplier->code ?? '') : ($suggestedCode ?? ''));
    $title = $isEdit ? 'Edit Supplier' : 'Create New Supplier';
    $subtitle = $isEdit ? 'Update supplier information' : 'Add a new supplier to your system';
    $buttonText = $isEdit ? 'Update Supplier' : 'Create Supplier';
@endphp

<div class="max-w-4xl mx-auto" x-data="supplierForm({{ json_encode($supplierData) }}, {{ $isEdit ? 'true' : 'false' }})">
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

            <!-- Company Information -->
            <div class="space-y-6">
                <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Company Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Company Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Company Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="name" 
                               id="name" 
                               x-model="formData.name"
                               required
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror"
                               placeholder="ABC Supplies Inc.">
                        <p class="mt-1 text-xs text-gray-500">Must be unique within your company.</p>
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
                               maxlength="20"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono @error('code') border-red-500 @enderror"
                               placeholder="SU01, SU02…">
                        <p class="mt-1 text-xs text-gray-500">Leave blank to auto-assign the next code (SU01, SU02…). You can enter your own code instead.</p>
                        @error('code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Tax ID -->
                    <div>
                        <label for="tax_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Tax ID
                        </label>
                        <input type="text" 
                               name="tax_id" 
                               id="tax_id" 
                               x-model="formData.tax_id"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('tax_id') border-red-500 @enderror"
                               placeholder="TAX-123456">
                        @error('tax_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Address -->
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                        Address
                    </label>
                    <textarea name="address" 
                              id="address" 
                              rows="3"
                              x-model="formData.address"
                              class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('address') border-red-500 @enderror"
                              placeholder="Street address, city, state, postal code..."></textarea>
                    @error('address')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Contact Information -->
            <div class="space-y-6 pt-6 border-t border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Contact Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Contact Person -->
                    <div>
                        <label for="contact_person" class="block text-sm font-medium text-gray-700 mb-2">
                            Contact Person
                        </label>
                        <input type="text" 
                               name="contact_person" 
                               id="contact_person" 
                               x-model="formData.contact_person"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('contact_person') border-red-500 @enderror"
                               placeholder="John Doe">
                        @error('contact_person')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email
                        </label>
                        <input type="email" 
                               name="email" 
                               id="email" 
                               x-model="formData.email"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('email') border-red-500 @enderror"
                               placeholder="contact@supplier.com">
                        <p class="mt-1 text-xs text-gray-500">Optional. Must be unique per supplier when provided.</p>
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Phone -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                            Phone
                        </label>
                        <input type="text" 
                               name="phone" 
                               id="phone" 
                               x-model="formData.phone"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('phone') border-red-500 @enderror"
                               placeholder="+1234567890">
                        <p class="mt-1 text-xs text-gray-500">Optional. Must be unique per supplier when provided.</p>
                        @error('phone')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- WhatsApp -->
                    <div>
                        <label for="whatsapp" class="block text-sm font-medium text-gray-700 mb-2">
                            WhatsApp
                        </label>
                        <input type="text" 
                               name="whatsapp" 
                               id="whatsapp" 
                               x-model="formData.whatsapp"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('whatsapp') border-red-500 @enderror"
                               placeholder="+1234567890">
                        <p class="mt-1 text-xs text-gray-500">Include country code (e.g., +1234567890)</p>
                        @error('whatsapp')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="space-y-6 pt-6 border-t border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Additional Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Status -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                            Status
                        </label>
                        <select name="status" 
                                id="status" 
                                x-model="formData.status"
                                class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('status') border-red-500 @enderror">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        @error('status')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Balance (Only in Create Form) -->
                    @if(!$isEdit)
                        <div>
                            <label for="balance" class="block text-sm font-medium text-gray-700 mb-2">
                                Opening balance
                            </label>
                            <input type="number" 
                                   name="balance" 
                                   id="balance" 
                                   step="0.01"
                                   x-model="formData.balance"
                                   class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('balance') border-red-500 @enderror"
                                   placeholder="0.00">
                            <p class="mt-1 text-xs text-gray-500">{{ \App\Support\PartyBalance::supplierOpeningHint() }}</p>
                            @error('balance')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                        Notes
                    </label>
                    <textarea name="notes" 
                              id="notes" 
                              rows="3"
                              x-model="formData.notes"
                              class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('notes') border-red-500 @enderror"
                              placeholder="Additional notes about the supplier..."></textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('suppliers.index') }}"
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
function supplierForm(supplierData = null, isEdit = false) {
    return {
        formData: {
            name: supplierData?.name || '',
            code: supplierData?.code || '',
            contact_person: supplierData?.contact_person || '',
            email: supplierData?.email || '',
            phone: supplierData?.phone || '',
            whatsapp: supplierData?.whatsapp || '',
            address: supplierData?.address || '',
            tax_id: supplierData?.tax_id || '',
            status: supplierData?.status || 'active',
            balance: supplierData?.balance ?? 0,
            notes: supplierData?.notes || '',
        },

        submitForm(event) {
            // Let the form submit normally
            event.target.submit();
        }
    }
}
</script>

