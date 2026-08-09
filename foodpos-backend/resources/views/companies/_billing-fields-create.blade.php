            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-2">
                <div class="flex items-center md:col-span-2">
                    <input type="hidden" name="billing_enabled" value="0">
                    <input type="checkbox" name="billing_enabled" id="billing_enabled" value="1"
                           {{ old('billing_enabled', true) ? 'checked' : '' }}
                           class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="billing_enabled" class="ml-2 block text-sm text-gray-700">Track this tenant in platform billing</label>
                </div>

                <div>
                    <label for="billing_currency" class="block text-sm font-medium text-gray-700 mb-2">Payment currency</label>
                    <select name="billing_currency" id="billing_currency" class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Same as POS currency</option>
                        @foreach($billingCurrencies as $code)
                            <option value="{{ $code }}" @selected(old('billing_currency') === $code)>{{ $code }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="billing_interval" class="block text-sm font-medium text-gray-700 mb-2">Recurring interval (after trial)</label>
                    <select name="billing_interval" id="billing_interval" class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Not set yet</option>
                        @foreach($billingIntervals as $key => $interval)
                            <option value="{{ $key }}" @selected(old('billing_interval', 'monthly') === $key)>{{ $interval['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="billing_amount" class="block text-sm font-medium text-gray-700 mb-2">Amount per interval</label>
                    <input type="number" step="0.01" min="0" name="billing_amount" id="billing_amount"
                           value="{{ old('billing_amount', '') }}"
                           class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                           placeholder="e.g. 99 monthly, 0 for free">
                </div>

                <div class="md:col-span-2">
                    <label for="billing_notes" class="block text-sm font-medium text-gray-700 mb-2">Billing notes</label>
                    <textarea name="billing_notes" id="billing_notes" rows="2" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('billing_notes') }}</textarea>
                </div>
            </div>
