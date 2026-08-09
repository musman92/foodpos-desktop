            <!-- Platform billing (super admin) -->
            <div class="border-t border-gray-200 pt-6 mt-2">
                <h3 class="text-base font-semibold text-gray-900 mb-1">Platform billing</h3>
                <p class="text-sm text-gray-500 mb-4">Per-tenant price, due date, and trial. Set amount to <strong>0</strong> and a far-future due date (e.g. 2 years) for complimentary access. Demo companies are always excluded.</p>

                @if($company->demo)
                    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        This is a demo company — billing is disabled automatically.
                    </div>
                @endif

                @if(isset($outstandingBalance) && $outstandingBalance > 0)
                    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-amber-900">Outstanding balance</p>
                            <p class="text-lg font-bold text-amber-800">{{ format_platform_currency($outstandingBalance, $company->billingCurrency()) }}</p>
                        </div>
                        <a href="{{ route('platform-invoices.index', ['company_id' => $company->id, 'status' => 'sent']) }}" class="text-sm font-medium text-indigo-700 hover:text-indigo-900">View invoices</a>
                    </div>
                @endif

                @if($company->isOnTrial())
                    <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                        <strong>Free trial</strong> until {{ $company->trial_ends_at->format('M j, Y') }}.
                        Billing starts {{ $company->billing_starts_at?->format('M j, Y') ?? 'after trial' }}.
                    </div>
                @elseif($company->billing_starts_at && $company->billing_starts_at->isFuture())
                    <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                        Billing starts on {{ $company->billing_starts_at->format('M j, Y') }}.
                    </div>
                @endif

                <div class="flex items-center mb-4">
                    <input type="hidden" name="billing_enabled" value="0">
                    <input type="checkbox" name="billing_enabled" id="billing_enabled" value="1"
                           {{ old('billing_enabled', $company->billing_enabled) && ! $company->demo ? 'checked' : '' }}
                           @disabled($company->demo)
                           class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="billing_enabled" class="ml-2 block text-sm text-gray-700">Enable platform billing for this tenant</label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="billing_currency" class="block text-sm font-medium text-gray-700 mb-2">Payment currency</label>
                        <select name="billing_currency" id="billing_currency" class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Same as POS currency ({{ $company->currency ?? 'USD' }})</option>
                            @foreach($billingCurrencies as $code)
                                <option value="{{ $code }}" @selected(old('billing_currency', $company->billing_currency) === $code)>{{ $code }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="billing_interval" class="block text-sm font-medium text-gray-700 mb-2">Recurring interval</label>
                        <select name="billing_interval" id="billing_interval" class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Not set</option>
                            @foreach($billingIntervals as $key => $interval)
                                <option value="{{ $key }}" @selected(old('billing_interval', $company->billing_interval) === $key)>{{ $interval['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="billing_amount" class="block text-sm font-medium text-gray-700 mb-2">Amount per interval</label>
                        <input type="number" step="0.01" min="0" name="billing_amount" id="billing_amount"
                               value="{{ old('billing_amount', $company->billing_amount) }}"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="0 for free / complimentary tenants">
                        <p class="mt-1 text-xs text-gray-500">Use <strong>0</strong> for free tenants — set a due date instead of invoicing.</p>
                    </div>

                    <div>
                        <label for="billing_due_date" class="block text-sm font-medium text-gray-700 mb-2">Billing due date (paid through)</label>
                        <input type="date" name="billing_due_date" id="billing_due_date"
                               value="{{ old('billing_due_date', $company->billing_due_date?->toDateString()) }}"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-500">Tenant can use the system until this date. Set 1–2 years ahead for complimentary access.</p>
                    </div>

                    <div>
                        <label for="trial_ends_at" class="block text-sm font-medium text-gray-700 mb-2">Trial ends</label>
                        <input type="date" name="trial_ends_at" id="trial_ends_at"
                               value="{{ old('trial_ends_at', $company->trial_ends_at?->toDateString()) }}"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="billing_starts_at" class="block text-sm font-medium text-gray-700 mb-2">Start charging after</label>
                        <input type="date" name="billing_starts_at" id="billing_starts_at"
                               value="{{ old('billing_starts_at', $company->billing_starts_at?->toDateString()) }}"
                               class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-500">First invoice should be generated after this date (usually same as trial end).</p>
                    </div>

                    <div class="md:col-span-2">
                        <label for="billing_notes" class="block text-sm font-medium text-gray-700 mb-2">Billing notes</label>
                        <textarea name="billing_notes" id="billing_notes" rows="2"
                                  class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Internal notes or default invoice footer">{{ old('billing_notes', $company->billing_notes) }}</textarea>
                    </div>
                </div>

                @if($company->isBillable() && (float) $company->billing_amount > 0 && \App\Support\TenantBilling::shouldChargeYet($company))
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('platform-invoices.create', ['company_id' => $company->id]) }}" class="inline-flex items-center px-3 py-2 border border-indigo-300 rounded-lg text-sm font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100">
                            Create invoice
                        </a>
                        <form action="{{ route('platform-invoices.generate', $company) }}" method="POST" class="inline" onsubmit="return confirm('Generate invoice from billing plan?');">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">
                                Generate from plan
                            </button>
                        </form>
                    </div>
                @elseif($company->isBillable() && (float) $company->billing_amount <= 0)
                    <p class="mt-4 text-sm text-gray-500">Complimentary plan — no invoice needed while billing due date is in the future.</p>
                @endif
            </div>
