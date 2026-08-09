<?php

namespace App\Support;

use App\Models\Company;
use App\Models\PlatformInvoice;
use App\Models\PlatformInvoicePayment;
use Illuminate\Support\Collection;

class PlatformBillingMetrics
{
    /**
     * @return array{
     *     outstanding_by_currency: list<array{currency: string, amount: float}>,
     *     collected_mtd_by_currency: list<array{currency: string, amount: float}>,
     *     invoiced_mtd_by_currency: list<array{currency: string, amount: float}>,
     *     overdue_count: int,
     *     open_invoices: int,
     *     paid_mtd: int
     * }
     */
    public static function summary(): array
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $openStatuses = ['sent', 'partial'];

        $openInvoices = PlatformInvoice::query()
            ->forBillableTenants()
            ->whereIn('status', $openStatuses)
            ->with('payments')
            ->get();

        $outstandingByCurrency = $openInvoices
            ->groupBy(fn (PlatformInvoice $invoice) => strtoupper($invoice->currency ?: 'USD'))
            ->map(fn ($group, $currency) => [
                'currency' => $currency,
                'amount' => round($group->sum(fn (PlatformInvoice $invoice) => $invoice->balance_due), 2),
            ])
            ->values()
            ->all();

        $collectedMtdByCurrency = PlatformInvoicePayment::query()
            ->whereBetween('payment_date', [$monthStart, $monthEnd])
            ->whereHas('invoice', fn ($q) => $q->forBillableTenants()->where('status', '!=', 'void'))
            ->with('invoice:id,currency')
            ->get()
            ->groupBy(fn (PlatformInvoicePayment $payment) => strtoupper($payment->invoice?->currency ?: 'USD'))
            ->map(fn ($group, $currency) => [
                'currency' => $currency,
                'amount' => round($group->sum('amount'), 2),
            ])
            ->values()
            ->all();

        $invoicedMtdByCurrency = PlatformInvoice::query()
            ->forBillableTenants()
            ->whereBetween('issue_date', [$monthStart, $monthEnd])
            ->where('status', '!=', 'void')
            ->get(['currency', 'total_amount'])
            ->groupBy(fn (PlatformInvoice $invoice) => strtoupper($invoice->currency ?: 'USD'))
            ->map(fn ($group, $currency) => [
                'currency' => $currency,
                'amount' => round($group->sum('total_amount'), 2),
            ])
            ->values()
            ->all();

        $overdueCount = $openInvoices->filter(fn (PlatformInvoice $invoice) => $invoice->isOverdue())->count();

        $paidMtd = PlatformInvoice::query()
            ->forBillableTenants()
            ->where('status', 'paid')
            ->whereBetween('updated_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        return [
            'outstanding_by_currency' => $outstandingByCurrency,
            'outstanding_tenants' => TenantBilling::tenantsWithOutstanding(),
            'collected_mtd_by_currency' => $collectedMtdByCurrency,
            'invoiced_mtd_by_currency' => $invoicedMtdByCurrency,
            'overdue_count' => $overdueCount,
            'open_invoices' => $openInvoices->count(),
            'paid_mtd' => $paidMtd,
        ];
    }

    /**
     * @return Collection<int, Company>
     */
    public static function billableTenants(): Collection
    {
        return Company::billable()
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, PlatformInvoice>
     */
    public static function recentInvoices(int $limit = 8): Collection
    {
        return PlatformInvoice::with(['company', 'payments'])
            ->forBillableTenants()
            ->latest('issue_date')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return list<array{month: string, label: string, collected: float, invoiced: float}>
     */
    public static function monthlySeries(int $months = 6): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $series = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $from = $month->copy()->startOfMonth()->toDateString();
            $to = $month->copy()->endOfMonth()->toDateString();

            $collected = (float) PlatformInvoicePayment::query()
                ->whereBetween('payment_date', [$from, $to])
                ->whereHas('invoice', fn ($q) => $q->forBillableTenants()->where('status', '!=', 'void'))
                ->sum('amount');

            $invoiced = (float) PlatformInvoice::query()
                ->forBillableTenants()
                ->whereBetween('issue_date', [$from, $to])
                ->where('status', '!=', 'void')
                ->sum('total_amount');

            $series[] = [
                'month' => $month->format('Y-m'),
                'label' => $month->format('M Y'),
                'collected' => round($collected, 2),
                'invoiced' => round($invoiced, 2),
            ];
        }

        return $series;
    }

    /**
     * @return array{
     *     total_invoiced: float,
     *     total_collected: float,
     *     total_outstanding: float,
     *     by_company: list<array{company: Company, currency: string, invoiced: float, collected: float, outstanding: float, billing_interval_label: string, billing_amount: float}>,
     *     billable_tenants: Collection<int, Company>
     * }
     */
    public static function report(?int $companyId, string $startDate, string $endDate): array
    {
        $invoiceQuery = PlatformInvoice::query()
            ->forBillableTenants()
            ->where('status', '!=', 'void')
            ->whereBetween('issue_date', [$startDate, $endDate]);

        if ($companyId) {
            $invoiceQuery->where('company_id', $companyId);
        }

        $totalInvoiced = (float) (clone $invoiceQuery)->sum('total_amount');

        $paymentQuery = PlatformInvoicePayment::query()
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->whereHas('invoice', function ($q) use ($companyId) {
                $q->forBillableTenants()->where('status', '!=', 'void');
                if ($companyId) {
                    $q->where('company_id', $companyId);
                }
            });

        $totalCollected = (float) $paymentQuery->sum('amount');

        $outstandingQuery = PlatformInvoice::query()
            ->forBillableTenants()
            ->whereIn('status', ['sent', 'partial']);

        if ($companyId) {
            $outstandingQuery->where('company_id', $companyId);
        }

        $totalOutstanding = 0.0;
        foreach ($outstandingQuery->with('payments')->get() as $invoice) {
            $totalOutstanding += $invoice->balance_due;
        }

        $byCompany = Company::query()
            ->billable()
            ->when($companyId, fn ($q) => $q->where('id', $companyId))
            ->orderBy('name')
            ->get()
            ->map(function (Company $company) use ($startDate, $endDate) {
                $invoiced = (float) PlatformInvoice::query()
                    ->where('company_id', $company->id)
                    ->where('status', '!=', 'void')
                    ->whereBetween('issue_date', [$startDate, $endDate])
                    ->sum('total_amount');

                $collected = (float) PlatformInvoicePayment::query()
                    ->whereBetween('payment_date', [$startDate, $endDate])
                    ->whereHas('invoice', fn ($q) => $q->where('company_id', $company->id)->where('status', '!=', 'void'))
                    ->sum('amount');

                $outstanding = PlatformInvoice::query()
                    ->where('company_id', $company->id)
                    ->whereIn('status', ['sent', 'partial'])
                    ->with('payments')
                    ->get()
                    ->sum(fn (PlatformInvoice $inv) => $inv->balance_due);

                return [
                    'company' => $company,
                    'currency' => $company->billingCurrency(),
                    'billing_interval_label' => $company->billingIntervalLabel(),
                    'billing_amount' => round((float) ($company->billing_amount ?? 0), 2),
                    'billing_due_date' => $company->billing_due_date,
                    'trial_ends_at' => $company->trial_ends_at,
                    'billing_starts_at' => $company->billing_starts_at,
                    'billing_status' => $company->billingStatusLabel(),
                    'invoiced' => round($invoiced, 2),
                    'collected' => round($collected, 2),
                    'outstanding' => round($outstanding, 2),
                ];
            })
            ->values()
            ->all();

        return [
            'total_invoiced' => round($totalInvoiced, 2),
            'total_collected' => round($totalCollected, 2),
            'total_outstanding' => round($totalOutstanding, 2),
            'by_company' => $byCompany,
            'billable_tenants' => self::billableTenants(),
        ];
    }
}
