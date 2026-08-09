<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\EmployeeLedgerEntry;
use App\Models\Order;
use App\Models\PartyBalanceAdjustment;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\PosCreditService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AccountStatementService
{
    public function __construct(
        protected PosCreditService $posCreditService
    ) {}

    /**
     * @return array{
     *     party: Customer,
     *     lines: list<array<string, mixed>>,
     *     opening_balance: float,
     *     closing_balance: float
     * }
     */
    public function customerStatement(
        Customer $customer,
        int $companyId,
        int $branchId,
        ?string $from = null,
        ?string $to = null
    ): array {
        $lines = collect();

        $orders = Order::withoutGlobalScopes(['tenant', 'branch'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->with('moneySource:id,name,type')
            ->orderBy('id')
            ->get([
                'id',
                'order_number',
                'customer_id',
                'total_amount',
                'paid_amount',
                'paid_at_sale',
                'payment_method',
                'payment_status',
                'money_source_id',
                'completed_at',
                'created_at',
            ]);

        $payments = CustomerPayment::withoutGlobalScopes(['tenant', 'branch'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('customer_id', $customer->id)
            ->with('moneySource:id,name,type')
            ->orderBy('id')
            ->get(['id', 'payment_number', 'payment_date', 'amount', 'discount_amount', 'kind', 'money_source_id', 'created_at']);

        $paymentAllocations = $this->customerPaymentAllocationsByOrder($orders, $payments);

        foreach ($orders as $order) {
            if (! $this->orderBelongsOnCustomerStatement($order)) {
                continue;
            }

            $completedAt = Carbon::parse($order->completed_at ?? $order->created_at);
            $displayDate = $completedAt->copy()->startOfDay();
            $lines->push($this->line(
                date: $displayDate,
                type: 'order_credit',
                label: 'Credit sale',
                reference: $order->order_number,
                url: route('order-management.show', $order),
                debit: round((float) $order->total_amount, 2),
                credit: 0.0,
                sortAt: $completedAt,
                sortId: $order->id,
                sortSequence: 10,
            ));

            $paidAtSale = $this->paidAtSaleForOrder($order, $paymentAllocations);
            if ($paidAtSale <= 0) {
                continue;
            }

            $lines->push($this->line(
                date: $displayDate,
                type: 'order_payment',
                label: 'Payment at sale',
                reference: $order->order_number,
                url: route('order-management.show', $order),
                debit: 0.0,
                credit: $paidAtSale,
                sortAt: $completedAt->copy()->addMicrosecond(),
                sortId: $order->id,
                sortSequence: 20,
                moneySource: $order->moneySource?->name
                    ?? ($order->payment_method && $order->payment_method !== 'credit'
                        ? ucfirst($order->payment_method)
                        : null)
            ));
        }

        foreach ($payments as $payment) {
            $applied = round((float) $payment->amount + (float) ($payment->discount_amount ?? 0), 2);
            if ($applied <= 0) {
                continue;
            }

            $recordedAt = Carbon::parse($payment->created_at);
            $businessDate = Carbon::parse($payment->payment_date);
            $displayDate = $businessDate->copy()->startOfDay();
            $label = match ($payment->kind ?? CustomerPayment::KIND_COLLECTION) {
                CustomerPayment::KIND_ADVANCE => 'Advance received',
                default => (float) ($payment->discount_amount ?? 0) > 0
                    ? 'Payment received (incl. write-off)'
                    : 'Payment received',
            };

            $lines->push($this->line(
                date: $displayDate,
                type: 'customer_payment',
                label: $label,
                reference: $payment->payment_number,
                url: route('customer-payments.show', $payment),
                debit: 0.0,
                credit: $applied,
                sortAt: $recordedAt,
                sortId: $payment->id,
                sortSequence: 30,
                moneySource: $payment->moneySource?->name
            ));
        }

        $adjustments = PartyBalanceAdjustment::query()
            ->where('company_id', $companyId)
            ->where('party_type', PartyBalanceAdjustment::PARTY_CUSTOMER)
            ->where('party_id', $customer->id)
            ->orderBy('id')
            ->get(['id', 'previous_balance', 'new_balance', 'reason', 'created_at']);

        $legacyOpeningId = $this->legacyOpeningAdjustmentId($adjustments);

        foreach ($adjustments as $adjustment) {
            $delta = round((float) $adjustment->new_balance - (float) $adjustment->previous_balance, 2);
            if (abs($delta) < 0.001) {
                continue;
            }

            [$type, $label, $reference] = $this->adjustmentPresentation($adjustment, $legacyOpeningId);
            $displayDate = Carbon::parse($adjustment->created_at)->startOfDay();
            $lines->push($this->line(
                date: $displayDate,
                type: $type,
                label: $label,
                reference: $reference,
                url: null,
                debit: $delta > 0 ? $delta : 0.0,
                credit: $delta < 0 ? abs($delta) : 0.0,
                sortAt: Carbon::parse($adjustment->created_at),
                sortId: $adjustment->id,
                sortSequence: 40,
            ));
        }

        $result = $this->finalizeStatement(
            $lines,
            $from,
            $to,
            'customer',
            round((float) ($customer->balance ?? 0), 2),
            Carbon::parse($customer->created_at ?? now())->startOfDay()
        );
        $result['party'] = $customer;

        return $result;
    }

    protected function orderBelongsOnCustomerStatement(Order $order): bool
    {
        if (! $order->customer_id) {
            return false;
        }

        if ($order->payment_method === 'credit') {
            return true;
        }

        if ($order->payment_status === 'partial') {
            return true;
        }

        if ($order->outstandingAmount() > 0) {
            return true;
        }

        return round((float) ($order->paid_at_sale ?? 0), 2) > 0
            || round((float) ($order->paid_amount ?? 0), 2) > 0;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @param  Collection<int, CustomerPayment>  $payments
     * @return array<int, float>
     */
    protected function customerPaymentAllocationsByOrder(Collection $orders, Collection $payments): array
    {
        $allocations = $orders->mapWithKeys(fn (Order $order) => [$order->id => 0.0])->all();
        $runningPaid = $orders->mapWithKeys(fn (Order $order) => [
            $order->id => round((float) ($order->paid_at_sale ?? 0), 2),
        ])->all();

        foreach ($payments as $payment) {
            $remaining = round((float) $payment->amount + (float) ($payment->discount_amount ?? 0), 2);
            if ($remaining <= 0) {
                continue;
            }

            foreach ($orders as $order) {
                if ($remaining <= 0.001) {
                    break;
                }

                $outstanding = round((float) $order->total_amount - ($runningPaid[$order->id] ?? 0), 2);
                if ($outstanding <= 0.001) {
                    continue;
                }

                $apply = round(min($remaining, $outstanding), 2);
                $runningPaid[$order->id] = round(($runningPaid[$order->id] ?? 0) + $apply, 2);
                $allocations[$order->id] = round(($allocations[$order->id] ?? 0) + $apply, 2);
                $remaining = round($remaining - $apply, 2);
            }
        }

        return $allocations;
    }

    /**
     * @param  array<int, float>  $paymentAllocations
     */
    protected function paidAtSaleForOrder(Order $order, array $paymentAllocations): float
    {
        $paidAtSale = round((float) ($order->paid_at_sale ?? 0), 2);
        if ($paidAtSale > 0) {
            return min($paidAtSale, round((float) $order->total_amount, 2));
        }

        $allocated = round((float) ($paymentAllocations[$order->id] ?? 0), 2);

        return max(0, round((float) ($order->paid_amount ?? 0) - $allocated, 2));
    }

    /**
     * @return array{
     *     party: Supplier,
     *     lines: list<array<string, mixed>>,
     *     opening_balance: float,
     *     closing_balance: float
     * }
     */
    public function supplierStatement(
        Supplier $supplier,
        int $companyId,
        int $branchId,
        ?string $from = null,
        ?string $to = null
    ): array {
        $lines = collect();

        $purchases = Purchase::withoutGlobalScopes(['tenant', 'branch'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('supplier_id', $supplier->id)
            ->with([
                'moneySource:id,name,type',
                'supplierPayments' => function ($query) {
                    $query->withoutGlobalScopes(['tenant', 'branch']);
                },
            ])
            ->orderBy('id')
            ->get([
                'id',
                'purchase_number',
                'purchase_date',
                'total_amount',
                'paid_amount',
                'money_source_id',
                'payment_method',
                'created_at',
            ]);

        $linkedSupplierPaymentIds = collect();
        /** @var array<int, array{purchase_id: int, reference: string, url: string}> $linkedSupplierPaymentRefs */
        $linkedSupplierPaymentRefs = [];

        foreach ($purchases as $purchase) {
            $recordedAt = Carbon::parse($purchase->created_at);
            $businessDate = Carbon::parse($purchase->purchase_date);
            $purchaseSortAt = $recordedAt;
            $displayDate = $businessDate->copy()->startOfDay();
            $lines->push($this->line(
                date: $displayDate,
                type: 'purchase',
                label: 'Purchase',
                reference: $purchase->purchase_number,
                url: route('purchases.show', $purchase),
                debit: 0.0,
                credit: round((float) $purchase->total_amount, 2),
                sortAt: $purchaseSortAt,
                sortId: $purchase->id,
                sortSequence: 10,
            ));

            $paidAtPurchase = round((float) ($purchase->paid_amount ?? 0), 2);
            if ($paidAtPurchase <= 0) {
                continue;
            }

            $linkedAmount = round(
                $purchase->supplierPayments->sum(fn ($payment) => (float) ($payment->pivot->amount ?? 0)),
                2
            );
            foreach ($purchase->supplierPayments as $payment) {
                $linkedSupplierPaymentIds->push($payment->id);
                $linkedSupplierPaymentRefs[$payment->id] = [
                    'purchase_id' => $purchase->id,
                    'reference' => $purchase->purchase_number,
                    'url' => route('purchases.show', $purchase),
                ];
            }

            $unlinkedPaid = round($paidAtPurchase - $linkedAmount, 2);

            if ($unlinkedPaid <= 0) {
                continue;
            }

            $lines->push($this->line(
                date: $displayDate,
                type: 'purchase_payment',
                label: 'Payment at purchase',
                reference: $purchase->purchase_number,
                url: route('purchases.show', $purchase),
                debit: $unlinkedPaid,
                credit: 0.0,
                sortAt: $recordedAt->copy()->addMicrosecond(),
                sortId: $purchase->id,
                sortSequence: 20,
                moneySource: $purchase->moneySource?->name
                    ?? ($purchase->payment_method && $purchase->payment_method !== 'credit'
                        ? ucfirst($purchase->payment_method)
                        : null)
            ));
        }

        $payments = SupplierPayment::withoutGlobalScopes(['tenant', 'branch'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('supplier_id', $supplier->id)
            ->with('moneySource:id,name,type')
            ->orderBy('id')
            ->get(['id', 'payment_number', 'payment_date', 'total_amount', 'kind', 'money_source_id', 'payment_method', 'created_at']);

        foreach ($payments as $payment) {
            $recordedAt = Carbon::parse($payment->created_at);
            $businessDate = Carbon::parse($payment->payment_date);
            $paymentSortAt = $recordedAt;
            $displayDate = $businessDate->copy()->startOfDay();

            if ($linkedSupplierPaymentIds->contains($payment->id)) {
                $refs = $linkedSupplierPaymentRefs[$payment->id] ?? null;

                $lines->push($this->line(
                    date: $displayDate,
                    type: 'supplier_payment',
                    label: 'Payment at purchase',
                    reference: $refs['reference'] ?? $payment->payment_number,
                    url: $refs['url'] ?? route('supplier-payments.show', $payment),
                    debit: round((float) $payment->total_amount, 2),
                    credit: 0.0,
                    sortAt: $paymentSortAt,
                    sortId: $payment->id,
                    sortSequence: 30,
                    moneySource: $payment->moneySource?->name ?? ($payment->payment_method ? ucfirst($payment->payment_method) : null)
                ));

                continue;
            }

            $lines->push($this->line(
                date: $displayDate,
                type: 'supplier_payment',
                label: ($payment->kind ?? SupplierPayment::KIND_PAYMENT) === SupplierPayment::KIND_ADVANCE
                    ? 'Advance paid'
                    : 'Payment made',
                reference: $payment->payment_number,
                url: route('supplier-payments.show', $payment),
                debit: round((float) $payment->total_amount, 2),
                credit: 0.0,
                sortAt: $paymentSortAt,
                sortId: $payment->id,
                sortSequence: 30,
                moneySource: $payment->moneySource?->name ?? ($payment->payment_method ? ucfirst($payment->payment_method) : null)
            ));
        }

        $adjustments = PartyBalanceAdjustment::query()
            ->where('company_id', $companyId)
            ->where('party_type', PartyBalanceAdjustment::PARTY_SUPPLIER)
            ->where('party_id', $supplier->id)
            ->orderBy('id')
            ->get(['id', 'previous_balance', 'new_balance', 'reason', 'created_at']);

        $legacyOpeningId = $this->legacyOpeningAdjustmentId($adjustments);

        foreach ($adjustments as $adjustment) {
            $delta = round((float) $adjustment->new_balance - (float) $adjustment->previous_balance, 2);
            if (abs($delta) < 0.001) {
                continue;
            }

            [$type, $label, $reference] = $this->adjustmentPresentation($adjustment, $legacyOpeningId);
            $sortAt = $this->resolveAdjustmentSortAt($adjustment, $lines);
            $displayDate = $sortAt->copy()->startOfDay();
            $lines->push($this->line(
                date: $displayDate,
                type: $type,
                label: $label,
                reference: $reference,
                url: null,
                debit: $delta < 0 ? abs($delta) : 0.0,
                credit: $delta > 0 ? $delta : 0.0,
                sortAt: $sortAt,
                sortId: $adjustment->id,
                sortSequence: 40,
            ));
        }

        $result = $this->finalizeStatement(
            $lines,
            $from,
            $to,
            'supplier',
            round((float) ($supplier->balance ?? 0), 2),
            Carbon::parse($supplier->created_at ?? now())->startOfDay()
        );
        $result['party'] = $supplier;

        return $result;
    }

    /**
     * @return array{
     *     party: User,
     *     lines: list<array<string, mixed>>,
     *     opening_balance: float,
     *     closing_balance: float
     * }
     */
    public function employeeStatement(
        User $employee,
        int $companyId,
        int $branchId,
        ?string $from = null,
        ?string $to = null
    ): array {
        $lines = collect();

        $entries = EmployeeLedgerEntry::withoutGlobalScopes(['tenant', 'branch'])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('employee_id', $employee->id)
            ->with([
                'payment.moneySource:id,name,type',
                'payrollItem.payrollRun',
            ])
            ->orderBy('id')
            ->get();

        foreach ($entries as $entry) {
            $amount = round((float) $entry->amount, 2);
            if ($amount <= 0) {
                continue;
            }

            $businessDate = Carbon::parse($entry->entry_date);
            $recordedAt = Carbon::parse($entry->created_at ?? $entry->entry_date);
            $displayDate = $businessDate->copy()->startOfDay();
            $isCredit = $entry->direction === 'credit';
            $label = $entry->description
                ?: ucfirst(str_replace('_', ' ', (string) $entry->type));
            $reference = $entry->payment?->payment_number;
            $url = $entry->employee_payment_id
                ? route('employee-payments.show', $entry->employee_payment_id)
                : null;

            $payrollRun = $entry->payrollItem?->payrollRun;
            if ($payrollRun) {
                $periodStart = format_date($payrollRun->period_start);
                $periodEnd = format_date($payrollRun->period_end);
                $label = trim($label).' · Payroll '.$periodStart.' – '.$periodEnd;
                $reference = $payrollRun->payroll_number;
                $url = route('hr.payroll.show', $payrollRun);
            }

            $reference = $reference ?: ('LEDGER-'.$entry->id);

            $lines->push($this->line(
                date: $displayDate,
                type: 'employee_'.$entry->type,
                label: $label,
                reference: $reference,
                url: $url,
                debit: $isCredit ? 0.0 : $amount,
                credit: $isCredit ? $amount : 0.0,
                sortAt: $recordedAt,
                sortId: (int) $entry->id,
                sortSequence: 10,
                moneySource: $entry->payment?->moneySource?->name
            ));
        }

        $branchNet = 0.0;
        foreach ($lines as $row) {
            $branchNet = round($branchNet + $this->balanceDelta($row, 'employee'), 2);
        }

        $result = $this->finalizeStatement(
            $lines,
            $from,
            $to,
            'employee',
            $branchNet,
            Carbon::parse($employee->created_at ?? now())->startOfDay()
        );
        $result['party'] = $employee;

        return $result;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return array{lines: list<array<string, mixed>>, opening_balance: float, closing_balance: float}
     */
    protected function finalizeStatement(
        Collection $lines,
        ?string $from,
        ?string $to,
        string $partyType,
        float $partyBalance = 0.0,
        ?Carbon $seedDate = null
    ): array {
        $sorted = $lines->sortBy('sort_key')->values();

        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

        // Create-time / undocumented balances live on party.balance but may have no ledger line.
        // Seed absorbs that residual so closing matches outstanding.
        $allNet = 0.0;
        foreach ($sorted as $row) {
            $allNet = round($allNet + $this->balanceDelta($row, $partyType), 2);
        }
        $seed = round($partyBalance - $allNet, 2);

        $balanceBeforePeriod = $seed;
        $running = 0.0;
        $periodLines = [];
        $inPeriod = false;

        foreach ($sorted as $row) {
            /** @var Carbon $rowDate */
            $rowDate = $row['date'];
            $delta = $this->balanceDelta($row, $partyType);

            if ($toDate && $rowDate->gt($toDate)) {
                continue;
            }

            if ($fromDate && $rowDate->lt($fromDate)) {
                $balanceBeforePeriod = round($balanceBeforePeriod + $delta, 2);

                continue;
            }

            if (! $inPeriod) {
                $running = $balanceBeforePeriod;
                $inPeriod = true;
            }

            $running = round($running + $delta, 2);
            $row['balance'] = $running;
            $periodLines[] = $row;
        }

        $openingBalance = $balanceBeforePeriod;
        $closingBalance = $periodLines !== []
            ? (float) end($periodLines)['balance']
            : $openingBalance;

        $showOpeningRow = $fromDate !== null || abs($openingBalance) >= 0.01;
        if ($showOpeningRow) {
            $openingDate = $fromDate
                ? $fromDate->copy()
                : ($seedDate?->copy() ?? Carbon::now()->startOfDay());

            array_unshift($periodLines, [
                'date' => $openingDate,
                'date_display' => $openingDate->toDateString(),
                'type' => 'opening_balance',
                'label' => 'Opening balance',
                'reference' => $fromDate ? 'Brought forward' : 'Opening balance',
                'url' => null,
                'money_source' => null,
                'debit' => 0.0,
                'credit' => 0.0,
                'balance' => $openingBalance,
                'sort_at' => $openingDate->copy(),
                'sort_id' => 0,
                'sort_sequence' => 0,
                'sort_key' => $this->sortKey($openingDate->copy(), 0, 0),
            ]);
        }

        return [
            'lines' => $periodLines,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
        ];
    }

    /**
     * Only the party's first from-zero adjustment may be treated as a legacy opening
     * when it has no specific reason. Later 0 → X corrections stay "Balance adjustment".
     *
     * @param  Collection<int, PartyBalanceAdjustment>  $adjustments
     */
    protected function legacyOpeningAdjustmentId(Collection $adjustments): ?int
    {
        $first = $adjustments->sortBy('id')->first();
        if (! $first) {
            return null;
        }

        if (abs((float) $first->previous_balance) >= 0.01) {
            return null;
        }

        $reason = trim((string) ($first->reason ?? ''));
        if ($reason !== ''
            && strcasecmp($reason, 'Opening balance') !== 0
            && strcasecmp($reason, 'Manual adjustment') !== 0
        ) {
            return null;
        }

        return (int) $first->id;
    }

    /**
     * Opening balance label: explicit reason, or that one legacy first from-zero adjustment.
     * Returning to zero later and adjusting again does NOT create another opening row.
     *
     * @return array{0: string, 1: string, 2: string} type, label, reference
     */
    protected function adjustmentPresentation(PartyBalanceAdjustment $adjustment, ?int $legacyOpeningAdjustmentId = null): array
    {
        $reason = trim((string) ($adjustment->reason ?? ''));

        $isOpening = strcasecmp($reason, 'Opening balance') === 0
            || ($legacyOpeningAdjustmentId !== null && (int) $adjustment->id === $legacyOpeningAdjustmentId);

        if ($isOpening) {
            return ['opening_balance', 'Opening balance', 'Opening balance'];
        }

        return ['balance_adjustment', 'Balance adjustment', $reason !== '' ? $reason : 'Manual adjustment'];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function balanceDelta(array $row, string $partyType): float
    {
        $debit = (float) $row['debit'];
        $credit = (float) $row['credit'];

        // Supplier / employee: positive balance means amount payable (credit − debit).
        // Customer: positive balance means amount receivable (debit − credit).
        if (in_array($partyType, ['supplier', 'employee'], true)) {
            return round($credit - $debit, 2);
        }

        return round($debit - $credit, 2);
    }

    /**
     * @return array<string, mixed>
     */
    protected function line(
        Carbon $date,
        string $type,
        string $label,
        string $reference,
        ?string $url,
        float $debit,
        float $credit,
        Carbon $sortAt,
        int $sortId,
        int $sortSequence = 50,
        ?string $moneySource = null
    ): array {
        return [
            'date' => $date,
            'date_display' => $date->format('Y-m-d'),
            'type' => $type,
            'label' => $label,
            'reference' => $reference,
            'url' => $url,
            'money_source' => $moneySource,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'sort_at' => $sortAt,
            'sort_id' => $sortId,
            'sort_sequence' => $sortSequence,
            'sort_key' => $this->sortKey($sortAt, $sortId, $sortSequence),
        ];
    }

    /**
     * Purchase edit adjustments must appear after the purchase and any linked payments.
     *
     * @param  Collection<int, array<string, mixed>>  $lines
     */
    protected function resolveAdjustmentSortAt(PartyBalanceAdjustment $adjustment, Collection $lines): Carbon
    {
        $sortAt = Carbon::parse($adjustment->created_at);

        if (! preg_match('/Purchase #(\S+)\s+total changed/', $adjustment->reason ?? '', $matches)) {
            return $sortAt;
        }

        $purchaseNumber = $matches[1];
        $latestRelated = $lines
            ->filter(function (array $row) use ($purchaseNumber): bool {
                if (! in_array($row['type'], ['purchase', 'purchase_payment', 'supplier_payment'], true)) {
                    return false;
                }

                return ($row['reference'] ?? '') === $purchaseNumber;
            })
            ->map(fn (array $row) => $row['sort_at'])
            ->max();

        if ($latestRelated instanceof Carbon && $sortAt->lte($latestRelated)) {
            return $latestRelated->copy()->addMicrosecond();
        }

        return $sortAt;
    }

    protected function sortKey(Carbon $sortAt, int $id, int $sequence = 50): string
    {
        return $sortAt->format('Y-m-d H:i:s.u')
            .'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT)
            .'-'.str_pad((string) $id, 10, '0', STR_PAD_LEFT);
    }
}
