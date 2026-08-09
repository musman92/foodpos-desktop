@php
    $isEdit = isset($customer) && $customer->exists;
    $formAction = $isEdit ? route('customers.update', $customer) : route('customers.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $defaults = $isEdit ? $customer->toArray() : [];
    $customerData = [
        'name' => old('name', $defaults['name'] ?? ''),
        'code' => old('code', $isEdit ? ($defaults['code'] ?? '') : ($suggestedCode ?? '')),
        'email' => old('email', $defaults['email'] ?? ''),
        'phone' => old('phone', $defaults['phone'] ?? ''),
        'date_of_birth' => old(
            'date_of_birth',
            isset($defaults['date_of_birth']) && $defaults['date_of_birth']
                ? \Illuminate\Support\Carbon::parse($defaults['date_of_birth'])->format('Y-m-d')
                : ''
        ),
        'gender' => old('gender', $defaults['gender'] ?? ''),
        'notes' => old('notes', $defaults['notes'] ?? ''),
        'balance' => old('balance', $defaults['balance'] ?? 0),
        'is_active' => session()->hasOldInput()
            ? (bool) old('is_active')
            : ($defaults['is_active'] ?? true),
    ];
    $oldAddresses = old('addresses');
    if (is_array($oldAddresses) && $oldAddresses !== []) {
        $addressesData = array_values($oldAddresses);
    } else {
        $addressesData = ($isEdit && $customer->relationLoaded('addresses') && $customer->addresses)
            ? $customer->addresses->toArray()
            : [];
    }
    $title = $isEdit ? 'Edit Customer' : 'Create New Customer';
    $subtitle = $isEdit ? 'Update customer information and delivery addresses' : 'Add a new customer with delivery addresses';
    $buttonText = $isEdit ? 'Update Customer' : 'Create Customer';
@endphp

<div class="max-w-4xl mx-auto" x-data="customerForm({{ json_encode($customerData) }}, {{ json_encode($addressesData) }}, {{ $isEdit ? 'true' : 'false' }})">
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
                <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2">Basic Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               name="name" 
                               id="name" 
                               x-model="formData.name"
                               required
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('name') border-red-500 @enderror"
                               placeholder="John Doe">
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
                               placeholder="CU01, CU02…">
                        <p class="mt-1 text-xs text-gray-500">Leave blank to auto-assign the next code (CU01, CU02…). You can enter your own code instead.</p>
                        @error('code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                               placeholder="user@example.com">
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
                        <p class="mt-1 text-xs text-gray-500">Optional. Must be unique per customer when provided.</p>
                        @error('phone')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Date of Birth -->
                    <div>
                        <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-2">
                            Date of Birth
                        </label>
                        <input type="date" 
                               name="date_of_birth" 
                               id="date_of_birth" 
                               x-model="formData.date_of_birth"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('date_of_birth') border-red-500 @enderror">
                        @error('date_of_birth')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Gender -->
                    <div>
                        <label for="gender" class="block text-sm font-medium text-gray-700 mb-2">
                            Gender
                        </label>
                        <select name="gender" 
                                id="gender" 
                                x-model="formData.gender"
                                class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('gender') border-red-500 @enderror">
                            <option value="">Select gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                        @error('gender')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Status
                        </label>
                        <label class="flex items-center mt-2">
                            <input type="checkbox"
                                   name="is_active"
                                   value="1"
                                   x-model="formData.is_active"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-600">Active</span>
                        </label>
                    </div>
                </div>

                @if(!$isEdit)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Balance (only in create form) -->
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
                            <p class="mt-1 text-xs text-gray-500">{{ \App\Support\PartyBalance::customerOpeningHint() }}</p>
                            @error('balance')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                </div>
                @endif

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
                              placeholder="Additional notes about the customer..."></textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Addresses -->
            <div class="space-y-6 pt-6 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Delivery Addresses</h2>
                    <button type="button" 
                            @click="addAddress()"
                            class="inline-flex items-center px-3 py-2 h-10 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <i class="fas fa-plus mr-2"></i>
                        Add Address
                    </button>
                </div>

                <div class="space-y-4" x-show="addresses.length > 0">
                    <template x-for="(address, index) in addresses" :key="index">
                        <div class="border border-gray-200 rounded-lg p-4 space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-900">Address <span x-text="index + 1"></span></h3>
                                <button type="button" 
                                        @click="removeAddress(index)"
                                        class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>

                            @if($isEdit)
                                <input type="hidden" :name="'addresses[' + index + '][id]'" :value="address.id || ''">
                            @endif

                            <!-- Address Label/Name -->
                            <div>
                                <label :for="'address_label_' + index" class="block text-sm font-medium text-gray-700 mb-2">
                                    Address Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       :name="'addresses[' + index + '][label]'" 
                                       :id="'address_label_' + index"
                                       x-model="address.label"
                                       required
                                       class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                       placeholder="e.g., My Home, Work, Mom's House, Head Office">
                                <p class="mt-1 text-xs text-gray-500">Give this address a name that helps you identify it</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Contact Name -->
                                <div>
                                    <label :for="'contact_name_' + index" class="block text-sm font-medium text-gray-700 mb-2">
                                        Contact Name
                                    </label>
                                    <input type="text" 
                                           :name="'addresses[' + index + '][contact_name]'" 
                                           :id="'contact_name_' + index"
                                           x-model="address.contact_name"
                                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                           placeholder="Contact person name">
                                </div>

                                <!-- Contact Phone -->
                                <div>
                                    <label :for="'contact_phone_' + index" class="block text-sm font-medium text-gray-700 mb-2">
                                        Contact Phone
                                    </label>
                                    <input type="text" 
                                           :name="'addresses[' + index + '][contact_phone]'" 
                                           :id="'contact_phone_' + index"
                                           x-model="address.contact_phone"
                                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                           placeholder="+1234567890">
                                </div>
                            </div>

                            <!-- Address Line 1 -->
                            <div>
                                <label :for="'address_line_1_' + index" class="block text-sm font-medium text-gray-700 mb-2">
                                    Address Line 1 <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       :name="'addresses[' + index + '][address_line_1]'" 
                                       :id="'address_line_1_' + index"
                                       x-model="address.address_line_1"
                                       required
                                       class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                       placeholder="Street address, P.O. box">
                            </div>

                            <!-- Address Line 2 -->
                            <div>
                                <label :for="'address_line_2_' + index" class="block text-sm font-medium text-gray-700 mb-2">
                                    Address Line 2
                                </label>
                                <input type="text" 
                                       :name="'addresses[' + index + '][address_line_2]'" 
                                       :id="'address_line_2_' + index"
                                       x-model="address.address_line_2"
                                       class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                       placeholder="Apartment, suite, unit, building, floor, etc.">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <!-- City -->
                                <div>
                                    <label :for="'city_' + index" class="block text-sm font-medium text-gray-700 mb-2">
                                        City <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           :name="'addresses[' + index + '][city]'" 
                                           :id="'city_' + index"
                                           x-model="address.city"
                                           required
                                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                           placeholder="City">
                                </div>

                                <!-- State -->
                                <div>
                                    <label :for="'state_' + index" class="block text-sm font-medium text-gray-700 mb-2">
                                        State/Province
                                    </label>
                                    <input type="text" 
                                           :name="'addresses[' + index + '][state]'" 
                                           :id="'state_' + index"
                                           x-model="address.state"
                                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                           placeholder="State">
                                </div>

                                <!-- Postal Code -->
                                <div>
                                    <label :for="'postal_code_' + index" class="block text-sm font-medium text-gray-700 mb-2">
                                        Postal Code
                                    </label>
                                    <input type="text" 
                                           :name="'addresses[' + index + '][postal_code]'" 
                                           :id="'postal_code_' + index"
                                           x-model="address.postal_code"
                                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                           placeholder="12345">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Country -->
                                <div>
                                    <label :for="'country_' + index" class="block text-sm font-medium text-gray-700 mb-2">
                                        Country
                                    </label>
                                    <input type="text" 
                                           :name="'addresses[' + index + '][country]'" 
                                           :id="'country_' + index"
                                           x-model="address.country"
                                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                           placeholder="Country">
                                </div>

                                <!-- Default Address -->
                                <div class="flex items-end">
                                    <label class="flex items-center">
                                        <input type="checkbox" 
                                               :name="'addresses[' + index + '][is_default]'" 
                                               value="1"
                                               x-model="address.is_default"
                                               @change="setDefaultAddress(index)"
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <span class="ml-2 text-sm text-gray-600">Set as default address</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Delivery Instructions -->
                            <div>
                                <label :for="'delivery_instructions_' + index" class="block text-sm font-medium text-gray-700 mb-2">
                                    Delivery Instructions
                                </label>
                                <textarea :name="'addresses[' + index + '][delivery_instructions]'" 
                                          :id="'delivery_instructions_' + index"
                                          rows="2"
                                          x-model="address.delivery_instructions"
                                          class="block w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                          placeholder="Special delivery instructions..."></textarea>
                            </div>
                        </div>
                    </template>
                </div>

                <div x-show="addresses.length === 0" class="text-center py-8 border-2 border-dashed border-gray-300 rounded-lg">
                    <p class="text-sm text-gray-500">No addresses added yet. Click "Add Address" to add one.</p>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                <a href="{{ route('customers.index') }}" 
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
function customerForm(customerData = null, addressesData = null, isEdit = false) {
    return {
        formData: {
            name: customerData?.name || '',
            code: customerData?.code || '',
            email: customerData?.email || '',
            phone: customerData?.phone || '',
            date_of_birth: customerData?.date_of_birth || '',
            gender: customerData?.gender || '',
            notes: customerData?.notes || '',
            balance: customerData?.balance || 0,
            is_active: customerData?.is_active ?? true,
        },
        addresses: addressesData && addressesData.length > 0 ? addressesData.map(addr => ({
            id: addr.id || null,
            type: addr.type || null,
            label: addr.label || '',
            contact_name: addr.contact_name || '',
            contact_phone: addr.contact_phone || '',
            address_line_1: addr.address_line_1 || '',
            address_line_2: addr.address_line_2 || '',
            city: addr.city || '',
            state: addr.state || '',
            postal_code: addr.postal_code || '',
            country: addr.country || '',
            latitude: addr.latitude || '',
            longitude: addr.longitude || '',
            is_default: addr.is_default || false,
            delivery_instructions: addr.delivery_instructions || '',
        })) : [],

        addAddress() {
            this.addresses.push({
                id: null,
                type: null,
                label: '',
                contact_name: '',
                contact_phone: '',
                address_line_1: '',
                address_line_2: '',
                city: '',
                state: '',
                postal_code: '',
                country: '',
                latitude: '',
                longitude: '',
                is_default: false,
                delivery_instructions: '',
            });
        },

        removeAddress(index) {
            this.addresses.splice(index, 1);
        },

        setDefaultAddress(index) {
            // Uncheck all other addresses
            this.addresses.forEach((addr, i) => {
                if (i !== index) {
                    addr.is_default = false;
                }
            });
        },

        submitForm(event) {
            // Let the form submit normally
            event.target.submit();
        }
    }
}
</script>

