@extends('layouts.app')

@section('title', 'Employee Payment')

@section('content')
@php
    $balances = $employeeBalances ?? [];
    $employeeOptions = $employees->map(function ($employee) use ($balances) {
        $profile = $employee->employeeProfile;
        $frequency = $profile?->pay_frequency ? ucfirst($profile->pay_frequency) : '—';
        $rate = (float) ($profile?->pay_rate ?? 0);
        $designation = $profile?->designation ?: 'Staff';
        $balance = (float) ($balances[(int) $employee->id] ?? 0);

        return [
            'id' => (int) $employee->id,
            'name' => $employee->name.' · '.$designation.' · '.$frequency.' · '.format_currency($rate),
            'employee_name' => $employee->name,
            'designation' => $designation,
            'pay_frequency' => $frequency,
            'pay_rate' => $rate,
            'pay_rate_label' => format_currency($rate),
            'ledger_balance' => $balance,
            'ledger_balance_label' => format_currency(abs($balance)),
        ];
    })->values();

    $selectedEmployeeIdValue = old('employee_id', $selectedEmployeeId);
    $companyConfig = get_company_config();
    $strictDirectPayRate = (bool) ($companyConfig['strict_direct_pay_rate'] ?? false);
    $currencyDecimals = (int) ($companyConfig['decimal_points'] ?? 2);
    $currencySymbol = get_currency_symbol($companyConfig['currency'] ?? 'USD');
    $currencyPosition = $companyConfig['currency_position'] ?? 'left';
    $amountStep = $currencyDecimals > 0 ? (1 / (10 ** $currencyDecimals)) : 1;
    $initialAmount = old('amount', $payrollItem?->remainingAmount());
    $oldAdjustmentIds = collect(old('adjustment_ids', []))->map(fn ($id) => (int) $id)->all();
@endphp
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-start justify-between gap-3">
        <div><h1 class="text-2xl font-bold">Employee payment</h1><p class="text-sm text-gray-500">Pay finalized payroll, direct wages, an advance, or a paid bonus.</p></div>
        <a href="{{ route('hr.adjustments.create', ['employee_id' => $selectedEmployeeId, 'type' => 'deduction']) }}" class="text-sm text-indigo-700">Record deduction instead</a>
    </div>
    @if($errors->any())<div class="p-4 rounded-lg bg-red-50 text-red-700 text-sm">{{ $errors->first() }}</div>@endif
    <form method="POST"
          action="{{ route('employee-payments.store') }}"
          x-data="{
              employeeId: @js($selectedEmployeeIdValue ? (string) $selectedEmployeeIdValue : ''),
              employees: @js($employeeOptions),
              pendingByEmployee: @js($pendingAdjustments ?? []),
              payablePayslipsByEmployee: @js($payablePayslips ?? []),
              selectedAdjustmentIds: @js($oldAdjustmentIds),
              payrollItemId: @js(old('payroll_item_id', $payrollItem?->id) ? (string) old('payroll_item_id', $payrollItem?->id) : ''),
              kind: @js(old('kind', $selectedKind)),
              amount: @js($initialAmount !== null && $initialAmount !== '' ? (string) $initialAmount : ''),
              amountTouched: @js($initialAmount !== null && $initialAmount !== ''),
              strictDirectPayRate: @js($strictDirectPayRate),
              currencyDecimals: @js($currencyDecimals),
              currencySymbol: @js($currencySymbol),
              currencyPosition: @js($currencyPosition),
              branchId: @js((string) ($payrollItem?->branch_id ?? current_branch_id() ?? '')),
              businessDatesByBranch: @js($businessDatesByBranch ?? []),
              businessDateLabelsByBranch: @js(collect($businessDatesByBranch ?? [])->mapWithKeys(
                  fn ($date, $id) => [(string) $id => format_date($date)]
              )->all()),
              lockedPayrollItem: @js((bool) $payrollItem),
              get businessDate() {
                  return this.businessDatesByBranch[this.branchId]
                      || this.businessDatesByBranch[String(this.branchId)]
                      || @js($businessDate);
              },
              get businessDateLabel() {
                  return this.businessDateLabelsByBranch[this.branchId]
                      || this.businessDateLabelsByBranch[String(this.branchId)]
                      || @js(format_date($businessDate));
              },
              get selectedEmployee() {
                  if (!this.employeeId) return null;
                  return this.employees.find((employee) => String(employee.id) === String(this.employeeId)) || null;
              },
              get currentBalance() {
                  return Number(this.selectedEmployee?.ledger_balance || 0);
              },
              get employeePayslips() {
                  if (!this.employeeId) return [];
                  return this.payablePayslipsByEmployee[String(this.employeeId)]
                      || this.payablePayslipsByEmployee[this.employeeId]
                      || [];
              },
              get selectedPayslip() {
                  if (!this.payrollItemId) return null;
                  return this.employeePayslips.find((row) => String(row.id) === String(this.payrollItemId)) || null;
              },
              get pendingAdjustments() {
                  if (!this.employeeId || !['wage', 'bonus'].includes(this.kind) || this.kind === 'payroll') {
                      return [];
                  }
                  const rows = this.pendingByEmployee[String(this.employeeId)] || this.pendingByEmployee[this.employeeId] || [];
                  if (this.kind === 'bonus') {
                      return rows.filter((row) => row.type === 'bonus');
                  }
                  return rows;
              },
              get selectedPending() {
                  return this.pendingAdjustments.filter((row) => this.selectedAdjustmentIds.includes(Number(row.id)));
              },
              get bonusTotal() {
                  return this.selectedPending
                      .filter((row) => row.type === 'bonus')
                      .reduce((sum, row) => sum + Number(row.remaining || 0), 0);
              },
              get deductionTotal() {
                  return this.selectedPending
                      .filter((row) => row.type === 'deduction')
                      .reduce((sum, row) => sum + Number(row.remaining || 0), 0);
              },
              get payRate() {
                  return Number(this.selectedEmployee?.pay_rate || 0);
              },
              get expectedTotal() {
                  if (this.kind === 'wage') {
                      const base = this.strictDirectPayRate ? this.payRate : Number(this.amount || 0);
                      return Math.round((base + this.bonusTotal - this.deductionTotal) * 100) / 100;
                  }
                  if (this.kind === 'bonus' && this.selectedPending.length) {
                      return Math.round(this.bonusTotal * 100) / 100;
                  }
                  return Number(this.amount || 0);
              },
              get suggestedAmount() {
                  if (this.kind === 'wage') {
                      const base = this.strictDirectPayRate ? this.payRate : (Number(this.amount || 0) || this.payRate);
                      return Math.round((this.payRate + this.bonusTotal - this.deductionTotal) * 100) / 100;
                  }
                  if (this.kind === 'bonus' && this.selectedPending.length) {
                      return Math.round(this.bonusTotal * 100) / 100;
                  }
                  return Number(this.amount || 0);
              },
              get cashToPay() {
                  return Number(this.amount || 0);
              },
              get balanceDelta() {
                  if (this.kind !== 'wage' || !this.strictDirectPayRate) return 0;
                  return Math.round((this.payRate + this.bonusTotal - this.deductionTotal - this.cashToPay) * 100) / 100;
              },
              get balanceAfter() {
                  return Math.round((this.currentBalance + this.balanceDelta) * 100) / 100;
              },
              get showPendingBlock() {
                  return this.kind !== 'payroll'
                      && ['wage', 'bonus'].includes(this.kind)
                      && this.employeeId
                      && this.pendingAdjustments.length > 0;
              },
              get showPaySummary() {
                  return this.kind === 'wage'
                      && this.selectedEmployee
                      && (this.strictDirectPayRate || this.selectedPending.length > 0);
              },
              formatMoney(value) {
                  const decimals = Number(this.currencyDecimals || 0);
                  const formatted = Number(value || 0).toLocaleString('en-US', {
                      minimumFractionDigits: decimals,
                      maximumFractionDigits: decimals,
                  });
                  return this.currencyPosition === 'right'
                      ? `${formatted} ${this.currencySymbol}`
                      : `${this.currencySymbol}${formatted}`;
              },
              balanceLabel(value) {
                  const amount = Math.abs(Number(value || 0));
                  if (Math.abs(Number(value || 0)) < 0.009) return this.formatMoney(0);
                  return this.formatMoney(amount) + (Number(value) >= 0 ? ' payable' : ' advance');
              },
              toggleAdjustment(id) {
                  const numericId = Number(id);
                  if (this.selectedAdjustmentIds.includes(numericId)) {
                      this.selectedAdjustmentIds = this.selectedAdjustmentIds.filter((item) => item !== numericId);
                  } else {
                      this.selectedAdjustmentIds = [...this.selectedAdjustmentIds, numericId];
                  }
                  this.suggestAmountIfNeeded();
              },
              syncPayslipSelection() {
                  if (this.kind !== 'payroll') {
                      if (!this.lockedPayrollItem) this.payrollItemId = '';
                      return;
                  }
                  const available = this.employeePayslips.map((row) => String(row.id));
                  if (this.payrollItemId && !available.includes(String(this.payrollItemId)) && !this.lockedPayrollItem) {
                      this.payrollItemId = '';
                  }
                  if (!this.payrollItemId && available.length === 1) {
                      this.payrollItemId = available[0];
                  }
                  if (this.selectedPayslip && !this.amountTouched) {
                      this.amount = String(this.selectedPayslip.remaining);
                  }
              },
              suggestAmountIfNeeded() {
                  if (this.amountTouched) return;
                  if (this.kind === 'payroll' && this.selectedPayslip) {
                      this.amount = String(this.selectedPayslip.remaining);
                      return;
                  }
                  if (this.kind === 'wage' && this.selectedEmployee) {
                      this.amount = String(Math.max(0, Math.round((this.payRate + this.bonusTotal - this.deductionTotal) * 100) / 100));
                      return;
                  }
                  if (this.kind === 'bonus' && this.selectedPending.length > 0) {
                      this.amount = String(this.bonusTotal);
                  }
              },
              pruneSelectedAdjustments() {
                  const available = new Set(this.pendingAdjustments.map((row) => Number(row.id)));
                  this.selectedAdjustmentIds = this.selectedAdjustmentIds.filter((id) => available.has(Number(id)));
                  this.suggestAmountIfNeeded();
              },
              init() {
                  this.$watch('employeeId', () => {
                      this.amountTouched = false;
                      this.pruneSelectedAdjustments();
                      this.syncPayslipSelection();
                  });
                  this.$watch('kind', () => {
                      this.amountTouched = false;
                      this.pruneSelectedAdjustments();
                      this.syncPayslipSelection();
                  });
                  this.$watch('payrollItemId', () => {
                      if (this.kind === 'payroll' && this.selectedPayslip) {
                          this.amount = String(this.selectedPayslip.remaining);
                          this.amountTouched = false;
                          if (this.selectedPayslip.branch_id) {
                              this.branchId = String(this.selectedPayslip.branch_id);
                          }
                      }
                  });
                  this.pruneSelectedAdjustments();
                  this.syncPayslipSelection();
              },
          }"
          x-init="init()"
          class="bg-white shadow rounded-lg p-6 space-y-5">
        @csrf
        @if($payrollItem)
            <input type="hidden" name="payroll_item_id" value="{{ $payrollItem->id }}">
            <div class="rounded-lg bg-green-50 p-4 text-sm text-green-800">Paying payslip from <strong>{{ $payrollItem->payrollRun->payroll_number }}</strong>. Remaining: <strong>{{ format_currency($payrollItem->remainingAmount()) }}</strong></div>
        @endif
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                @if(show_branch_ui())
                <label class="block text-sm font-medium mb-1">Branch *</label>
                <select name="branch_id"
                        required
                        class="w-full h-11 px-3 rounded-lg border-gray-300"
                        x-model="branchId">
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) old('branch_id', $payrollItem?->branch_id ?? current_branch_id()) === (int) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
                @else
                    <input type="hidden" name="branch_id" x-model="branchId" :value="branchId">
                @endif
            </div>

            <div>
                @if($payrollItem)
                    <label class="block text-sm font-medium mb-1">Employee *</label>
                    <div class="w-full h-11 px-3 rounded-lg border border-gray-300 bg-gray-50 text-gray-700 flex items-center">
                        {{ $payrollItem->employee->name }}
                    </div>
                    <input type="hidden" name="employee_id" value="{{ $payrollItem->employee_id }}">
                @else
                    <div x-data="searchableSelect({
                            options: employees,
                            value: employeeId,
                            maxResults: 200,
                            placeholder: 'Search employee…',
                            emptyMessage: 'No employees found',
                            onChange: (value) => { employeeId = value ? String(value) : ''; },
                        })"
                         x-init="init(); $watch('selectedValue', (value) => { employeeId = value ? String(value) : ''; })">
                        <x-searchable-select
                            label="Employee"
                            compact
                            useButtonOptions
                            required
                            id="employee_payment_employee"
                        >
                            <x-slot:hiddenInput>
                                <input type="hidden" name="employee_id" x-model="selectedValue" required>
                            </x-slot:hiddenInput>
                        </x-searchable-select>
                    </div>
                @endif

                <div x-show="selectedEmployee" x-cloak class="mt-2 rounded-lg border border-indigo-100 bg-indigo-50 px-3 py-2 text-sm text-indigo-900 space-y-1">
                    <div class="font-medium" x-text="selectedEmployee?.employee_name"></div>
                    <div class="text-xs text-indigo-700">
                        <span x-text="selectedEmployee?.designation"></span>
                        · Pay cycle: <span x-text="selectedEmployee?.pay_frequency"></span>
                        · Pay rate: <span class="font-semibold" x-text="selectedEmployee?.pay_rate_label"></span>
                    </div>
                    <div class="text-xs font-medium"
                         :class="currentBalance >= 0 ? 'text-green-800' : 'text-red-800'">
                        Current balance:
                        <span x-text="balanceLabel(currentBalance)"></span>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Payment type *</label>
                <select name="kind"
                        required
                        class="w-full h-11 px-3 rounded-lg border-gray-300"
                        x-model="kind"
                        {{ $payrollItem ? 'disabled' : '' }}>
                    @foreach(\App\Models\EmployeePayment::KINDS as $kind)
                        <option value="{{ $kind }}" @selected(old('kind', $selectedKind) === $kind)>
                            {{ match($kind) {
                                'payroll' => 'Payroll payment',
                                'wage' => 'Direct wage / salary',
                                'advance' => 'Advance',
                                'bonus' => 'Bonus paid now',
                            } }}
                        </option>
                    @endforeach
                </select>
                @if($payrollItem)<input type="hidden" name="kind" value="payroll">@endif
            </div>

            <div x-show="kind === 'payroll' && !lockedPayrollItem" x-cloak class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Payslip *</label>
                <select class="w-full h-11 px-3 rounded-lg border-gray-300"
                        x-model="payrollItemId"
                        :name="kind === 'payroll' ? 'payroll_item_id' : null"
                        :required="kind === 'payroll'"
                        :disabled="!employeeId || employeePayslips.length === 0">
                    <option value="">Select finalized payslip</option>
                    <template x-for="slip in employeePayslips" :key="slip.id">
                        <option :value="String(slip.id)" x-text="slip.label"></option>
                    </template>
                </select>
                <p x-show="employeeId && employeePayslips.length === 0" x-cloak class="mt-1 text-xs text-amber-700">
                    No unpaid finalized payslips for this employee. Finalize payroll first, or use Direct wage / salary.
                </p>
                <p x-show="selectedPayslip" x-cloak class="mt-1 text-xs text-gray-500">
                    Period <span x-text="selectedPayslip?.period_label"></span>
                    · Remaining <span x-text="formatMoney(selectedPayslip?.remaining || 0)"></span>
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Amount to pay *</label>
                <input type="number"
                       step="{{ $amountStep }}"
                       min="{{ $amountStep }}"
                       name="amount"
                       required
                       x-model="amount"
                       @input="amountTouched = true"
                       class="w-full h-11 px-3 rounded-lg border-gray-300">
                <p class="mt-1 text-xs text-gray-500">Cash leaving the till for this payment.</p>
                <button type="button"
                        x-show="kind === 'wage' && selectedEmployee"
                        x-cloak
                        @click="amount = String(Math.max(0, Math.round((payRate + bonusTotal - deductionTotal) * 100) / 100)); amountTouched = true"
                        class="mt-1 text-xs text-indigo-700">
                    Use expected total
                </button>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Business date</label>
                <input type="hidden" name="payment_date" :value="businessDate">
                <div class="w-full h-11 px-3 rounded-lg border border-gray-300 bg-gray-50 text-gray-700 flex items-center justify-between">
                    <span x-text="businessDateLabel"></span>
                    <i class="fas fa-lock text-xs text-gray-400" title="Locked to business date"></i>
                </div>
                <p class="mt-1 text-xs text-gray-500">Locked to the open shift date (or branch business date) so cash and reports stay aligned.</p>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Payment method *</label>
                <select name="payment_method" required class="w-full h-11 px-3 rounded-lg border-gray-300">
                    @foreach(['cash','transfer','card','online'] as $method)
                        <option value="{{ $method }}" @selected(old('payment_method', 'cash') === $method)>{{ ucfirst($method) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Money source *</label>
                <select name="money_source_id" required class="w-full h-11 px-3 rounded-lg border-gray-300">
                    <option value="">Select money source</option>
                    @foreach($moneySources as $source)
                        <option value="{{ $source->id }}" @selected((int) old('money_source_id') === (int) $source->id)>{{ $source->name }} ({{ ucfirst($source->type) }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Expense account</label>
                <div class="w-full h-11 px-3 rounded-lg border border-gray-300 bg-gray-50 text-gray-700 flex items-center justify-between">
                    <span>{{ $salaryAccount->name }}</span>
                    <i class="fas fa-lock text-xs text-gray-400" title="Fixed account"></i>
                </div>
                <p class="mt-1 text-xs text-gray-500">Fixed for consistent salary reporting.</p>
            </div>
        </div>

        <div x-show="showPendingBlock" x-cloak class="rounded-lg border border-gray-200 p-4 space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Pending bonuses & deductions</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Select items to settle with this payment. Selected items will be marked paid.</p>
                </div>
                <a :href="'{{ route('hr.adjustments.create') }}?employee_id=' + encodeURIComponent(employeeId || '')"
                   class="text-xs text-indigo-700 whitespace-nowrap">Add bonus / deduction</a>
            </div>

            <div class="space-y-2">
                <template x-for="row in pendingAdjustments" :key="row.id">
                    <label class="flex items-start gap-3 rounded-lg border border-gray-100 px-3 py-2 cursor-pointer hover:bg-gray-50">
                        <input type="checkbox"
                               class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                               :checked="selectedAdjustmentIds.includes(Number(row.id))"
                               @change="toggleAdjustment(row.id)">
                        <input type="hidden"
                               name="adjustment_ids[]"
                               :value="row.id"
                               x-bind:disabled="!selectedAdjustmentIds.includes(Number(row.id))">
                        <span class="flex-1 min-w-0">
                            <span class="flex items-center justify-between gap-3">
                                <span class="text-sm font-medium"
                                      :class="row.type === 'bonus' ? 'text-green-700' : 'text-red-700'"
                                      x-text="(row.type === 'bonus' ? 'Bonus' : 'Deduction') + ' · ' + row.notes"></span>
                                <span class="text-sm font-semibold whitespace-nowrap"
                                      :class="row.type === 'bonus' ? 'text-green-700' : 'text-red-700'"
                                      x-text="(row.type === 'bonus' ? '+' : '−') + formatMoney(row.remaining)"></span>
                            </span>
                            <span class="block text-xs text-gray-500 mt-0.5">
                                <span x-text="row.effective_date_label"></span>
                                · Remaining <span x-text="formatMoney(row.remaining)"></span>
                                <span x-show="Number(row.paid_amount) > 0">
                                    (of <span x-text="formatMoney(row.amount)"></span>)
                                </span>
                            </span>
                        </span>
                    </label>
                </template>
            </div>
        </div>

        <div x-show="showPaySummary" x-cloak class="rounded-lg bg-gray-50 px-3 py-3 text-sm space-y-1">
            <div class="flex justify-between gap-3">
                <span class="text-gray-600">Pay rate</span>
                <span x-text="formatMoney(payRate)"></span>
            </div>
            <div x-show="bonusTotal > 0" class="flex justify-between gap-3 text-green-700"><span>Bonuses</span><span x-text="'+' + formatMoney(bonusTotal)"></span></div>
            <div x-show="deductionTotal > 0" class="flex justify-between gap-3 text-red-700"><span>Deductions</span><span x-text="'−' + formatMoney(deductionTotal)"></span></div>
            <div class="flex justify-between gap-3 border-t border-gray-200 pt-2 text-gray-700">
                <span>Expected</span>
                <span x-text="formatMoney(payRate + bonusTotal - deductionTotal)"></span>
            </div>
            <div class="flex justify-between gap-3 font-semibold text-gray-900">
                <span>Paying now</span>
                <span x-text="formatMoney(cashToPay)"></span>
            </div>
            <template x-if="strictDirectPayRate">
                <div class="space-y-1 border-t border-gray-200 pt-2">
                    <div class="flex justify-between gap-3"
                         :class="balanceDelta > 0.009 ? 'text-green-700' : (balanceDelta < -0.009 ? 'text-red-700' : 'text-gray-700')">
                        <span>Balance change</span>
                        <span x-text="(balanceDelta > 0 ? '+' : '') + formatMoney(balanceDelta)"></span>
                    </div>
                    <div class="flex justify-between gap-3 font-medium"
                         :class="balanceAfter >= 0 ? 'text-green-800' : 'text-red-800'">
                        <span>Balance after</span>
                        <span x-text="balanceLabel(balanceAfter)"></span>
                    </div>
                    <p class="text-xs text-gray-500 pt-1">
                        Underpay increases payable balance; overpay reduces it for the next payment.
                    </p>
                </div>
            </template>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Notes</label>
            <textarea name="notes" rows="3" class="w-full px-3 py-2 rounded-lg border-gray-300">{{ old('notes') }}</textarea>
        </div>
        <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800">
            <strong>Advance:</strong> cash is paid now and automatically recovered from future payroll, up to the employee’s available pay.
            <strong>Deduction:</strong> create under Bonuses & deductions, then settle it here when paying wages.
        </div>
        <div class="flex justify-end gap-3 border-t pt-5">
            <a href="{{ route('employee-payments.index') }}" class="h-11 px-4 border rounded-lg flex items-center">Cancel</a>
            <button class="h-11 px-5 bg-indigo-600 text-white rounded-lg" :disabled="cashToPay <= 0">Record payment</button>
        </div>
    </form>
</div>
@endsection
