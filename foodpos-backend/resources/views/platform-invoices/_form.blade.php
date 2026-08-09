@php
    $isEdit = isset($invoice);
    $defaultIssue = old('issue_date', $isEdit ? $invoice->issue_date->toDateString() : now()->toDateString());
    $defaultDue = old('due_date', $isEdit ? $invoice->due_date->toDateString() : now()->addDays($defaultDueDays ?? 14)->toDateString());
    $lineItems = old('items', $isEdit
        ? $invoice->items->map(fn ($item) => [
            'description' => $item->description,
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
        ])->values()->all()
        : [['description' => 'FoodPOS subscription', 'quantity' => 1, 'unit_price' => 0]]
    );
    $selectedCompanyId = old('company_id', $isEdit ? $invoice->company_id : ($preselectedCompanyId ?? ''));
    $selectedCurrency = old('currency', $isEdit ? $invoice->currency : 'USD');
    $selectedInterval = old('billing_interval', $isEdit ? $invoice->billing_interval : '');
@endphp

@if($companies->isEmpty())
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        No billable tenants yet. Edit a company, enable billing, set amount/currency/interval, and ensure it is not marked as demo.
    </div>
@else
<form action="{{ $isEdit ? route('platform-invoices.update', $invoice) : route('platform-invoices.store') }}"
      method="POST"
      class="space-y-6"
      x-data="platformInvoiceForm({
          lines: @js($lineItems),
          taxAmount: {{ (float) old('tax_amount', $isEdit ? $invoice->tax_amount : 0) }},
          companyId: @js($selectedCompanyId ? (string) $selectedCompanyId : ''),
          currency: @js($selectedCurrency),
          billingInterval: @js($selectedInterval),
          billingContextUrl: @js(url('/platform-invoices/billing-context'))
      })">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="company_id" class="block text-sm font-medium text-gray-700 mb-2">Tenant company <span class="text-red-500">*</span></label>
            <select name="company_id" id="company_id" required x-model="companyId" @change="loadBillingPlan()" class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Select company</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}">
                        {{ $company->name }}
                        @if($company->billing_amount)
                            — {{ format_platform_currency((float) $company->billing_amount, $company->billingCurrency()) }} / {{ $company->billingIntervalLabel() }}
                        @endif
                    </option>
                @endforeach
            </select>
            @error('company_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-gray-500">Demo companies are excluded. Configure billing on the company edit page.</p>
        </div>

        <div class="rounded-lg border border-indigo-100 bg-indigo-50 p-4 text-sm" x-show="planSummary" x-cloak>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-semibold text-indigo-900" x-text="planSummary"></p>
                    <p class="mt-1 text-indigo-800" x-show="planAmountLabel" x-text="planAmountLabel"></p>
                </div>
                <button type="button" @click="applyBillingPlan()" class="flex-shrink-0 px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-semibold hover:bg-indigo-700">
                    Apply plan
                </button>
            </div>
        </div>

        <div>
            <label for="currency" class="block text-sm font-medium text-gray-700 mb-2">Payment currency</label>
            <select name="currency" id="currency" x-model="currency" @change="recalc()" class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @foreach($billingCurrencies as $code)
                    <option value="{{ $code }}">{{ $code }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="billing_interval" class="block text-sm font-medium text-gray-700 mb-2">Billing cycle</label>
            <select name="billing_interval" id="billing_interval" x-model="billingInterval" class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">One-time / custom</option>
                @foreach($billingIntervals as $key => $interval)
                    <option value="{{ $key }}">{{ $interval['label'] }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="issue_date" class="block text-sm font-medium text-gray-700 mb-2">Issue date <span class="text-red-500">*</span></label>
            <input type="date" name="issue_date" id="issue_date" value="{{ $defaultIssue }}" required class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="due_date" class="block text-sm font-medium text-gray-700 mb-2">Due date <span class="text-red-500">*</span></label>
            <input type="date" name="due_date" id="due_date" value="{{ $defaultDue }}" required class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="period_start" class="block text-sm font-medium text-gray-700 mb-2">Billing period start</label>
            <input type="date" name="period_start" id="period_start" x-model="periodStart" value="{{ old('period_start', $isEdit && $invoice->period_start ? $invoice->period_start->toDateString() : '') }}" class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="period_end" class="block text-sm font-medium text-gray-700 mb-2">Billing period end</label>
            <input type="date" name="period_end" id="period_end" x-model="periodEnd" value="{{ old('period_end', $isEdit && $invoice->period_end ? $invoice->period_end->toDateString() : '') }}" class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>

    <div>
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-900">Line items</h3>
            <button type="button" @click="addLine()" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                <i class="fas fa-plus mr-1"></i> Add line
            </button>
        </div>
        <div class="space-y-3">
            <template x-for="(line, index) in lines" :key="index">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start border border-gray-200 rounded-lg p-4 bg-gray-50">
                    <div class="md:col-span-5">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                        <input type="text" :name="`items[${index}][description]`" x-model="line.description" required class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Qty</label>
                        <input type="number" step="0.01" min="0.01" :name="`items[${index}][quantity]`" x-model.number="line.quantity" @input="recalc()" required class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Unit price</label>
                        <input type="number" step="0.01" min="0" :name="`items[${index}][unit_price]`" x-model.number="line.unit_price" @input="recalc()" required class="block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Line total</label>
                        <div class="h-10 flex items-center text-sm font-semibold text-gray-900" x-text="formatMoney(lineTotal(line))"></div>
                    </div>
                    <div class="md:col-span-1 flex items-end justify-end">
                        <button type="button" @click="removeLine(index)" x-show="lines.length > 1" class="p-2 text-red-600 hover:text-red-800" title="Remove line">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </template>
        </div>
        @error('items')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label for="tax_amount" class="block text-sm font-medium text-gray-700 mb-2">Tax amount</label>
            <input type="number" step="0.01" min="0" name="tax_amount" id="tax_amount" x-model.number="taxAmount" @input="recalc()" class="block w-full h-12 px-4 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-gray-600">Subtotal</span><span class="font-medium" x-text="formatMoney(subtotal)"></span></div>
            <div class="flex justify-between"><span class="text-gray-600">Tax</span><span class="font-medium" x-text="formatMoney(taxAmount)"></span></div>
            <div class="flex justify-between border-t border-gray-200 pt-2 text-base"><span class="font-semibold text-gray-900">Total</span><span class="font-bold text-gray-900" x-text="formatMoney(total)"></span></div>
        </div>
    </div>

    <div>
        <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
        <textarea name="notes" id="notes" rows="3" x-model="notes" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $isEdit ? $invoice->notes : '') }}</textarea>
    </div>

    @if(! $isEdit)
        <label class="flex items-center text-sm text-gray-700">
            <input type="checkbox" name="mark_sent" value="1" class="rounded border-gray-300 text-indigo-600 mr-2">
            Mark as sent immediately
        </label>
    @endif

    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
        <a href="{{ $isEdit ? route('platform-invoices.show', $invoice) : route('platform-invoices.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">
            {{ $isEdit ? 'Update invoice' : 'Create invoice' }}
        </button>
    </div>
</form>
@endif

<script>
function platformInvoiceForm(config) {
    return {
        lines: config.lines?.length ? config.lines : [{ description: '', quantity: 1, unit_price: 0 }],
        taxAmount: Number(config.taxAmount) || 0,
        companyId: config.companyId || '',
        currency: config.currency || 'USD',
        billingInterval: config.billingInterval || '',
        periodStart: document.getElementById('period_start')?.value || '',
        periodEnd: document.getElementById('period_end')?.value || '',
        notes: document.getElementById('notes')?.value || '',
        draft: null,
        planSummary: '',
        planAmountLabel: '',
        subtotal: 0,
        total: 0,
        billingContextUrl: config.billingContextUrl,
        init() {
            this.recalc();
            if (this.companyId) {
                this.loadBillingPlan();
            }
        },
        async loadBillingPlan() {
            this.planSummary = '';
            this.planAmountLabel = '';
            this.draft = null;
            if (!this.companyId) return;
            try {
                const response = await fetch(`${this.billingContextUrl}/${this.companyId}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await response.json();
                if (!response.ok) {
                    this.planSummary = data.error || 'Unable to load billing plan.';
                    return;
                }
                this.draft = data.draft;
                const company = data.company;
                this.planSummary = company.is_billable
                    ? `${company.billing_interval_label} plan configured`
                    : 'Billing is disabled for this tenant.';
                if (company.billing_amount > 0) {
                    this.planAmountLabel = `${this.formatMoney(company.billing_amount)} per ${company.billing_interval_label.toLowerCase()}`;
                }
            } catch (e) {
                this.planSummary = 'Could not load tenant billing plan.';
            }
        },
        applyBillingPlan() {
            if (!this.draft) return;
            this.currency = this.draft.currency;
            this.billingInterval = this.draft.interval || '';
            this.periodStart = this.draft.period_start;
            this.periodEnd = this.draft.period_end;
            document.getElementById('period_start').value = this.periodStart;
            document.getElementById('period_end').value = this.periodEnd;
            this.lines = this.draft.line_items.map(item => ({
                description: item.description,
                quantity: Number(item.quantity),
                unit_price: Number(item.unit_price),
            }));
            this.recalc();
        },
        addLine() {
            this.lines.push({ description: '', quantity: 1, unit_price: 0 });
        },
        removeLine(index) {
            if (this.lines.length > 1) {
                this.lines.splice(index, 1);
                this.recalc();
            }
        },
        lineTotal(line) {
            return (Number(line.quantity) || 0) * (Number(line.unit_price) || 0);
        },
        recalc() {
            this.subtotal = this.lines.reduce((sum, line) => sum + this.lineTotal(line), 0);
            this.total = this.subtotal + (Number(this.taxAmount) || 0);
        },
        formatMoney(amount) {
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: this.currency || 'USD' }).format(amount || 0);
        },
    };
}
</script>
