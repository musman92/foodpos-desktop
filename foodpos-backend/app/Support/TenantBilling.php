<?php

namespace App\Support;

use App\Models\Company;
use App\Models\PlatformInvoice;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TenantBilling
{
    /**
     * @return array<string, array{label: string, months: int}>
     */
    public static function intervals(): array
    {
        return config('platform_billing.intervals', []);
    }

    public static function intervalLabel(?string $interval): string
    {
        if ($interval === null || $interval === '') {
            return 'One-time';
        }

        return self::intervals()[$interval]['label'] ?? ucfirst(str_replace('_', ' ', $interval));
    }

    public static function intervalMonths(?string $interval): int
    {
        if ($interval === null || $interval === '') {
            return 1;
        }

        return (int) (self::intervals()[$interval]['months'] ?? 1);
    }

    public static function isOnTrial(Company $company): bool
    {
        return $company->trial_ends_at !== null && $company->trial_ends_at->isFuture();
    }

    public static function shouldChargeYet(Company $company): bool
    {
        if (self::isOnTrial($company)) {
            return false;
        }

        if ($company->billing_starts_at === null) {
            return true;
        }

        return ! $company->billing_starts_at->isFuture();
    }

    /**
     * Access is allowed through this date (billing due / paid-through).
     */
    public static function accessPaidThrough(Company $company): ?Carbon
    {
        if ($company->billing_due_date) {
            return $company->billing_due_date->copy()->endOfDay();
        }

        if ($company->subscription_expires_at) {
            return $company->subscription_expires_at->copy();
        }

        return null;
    }

    public static function hasActiveAccess(Company $company): bool
    {
        if ($company->status !== 'active') {
            return false;
        }

        if (self::isOnTrial($company)) {
            return true;
        }

        $paidThrough = self::accessPaidThrough($company);

        if ($paidThrough === null) {
            return true;
        }

        return $paidThrough->isFuture() || $paidThrough->isToday();
    }

    public static function outstandingBalance(Company $company): float
    {
        return round((float) PlatformInvoice::query()
            ->where('company_id', $company->id)
            ->whereIn('status', ['sent', 'partial'])
            ->with('payments')
            ->get()
            ->sum(fn (PlatformInvoice $invoice) => $invoice->balance_due), 2);
    }

    /**
     * @return array<string, float> currency => amount
     */
    public static function outstandingByCurrency(?int $companyId = null): array
    {
        $query = PlatformInvoice::query()
            ->forBillableTenants()
            ->whereIn('status', ['sent', 'partial'])
            ->with('payments');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $totals = [];
        foreach ($query->get() as $invoice) {
            $balance = $invoice->balance_due;
            if ($balance <= 0) {
                continue;
            }
            $currency = strtoupper($invoice->currency ?: 'USD');
            $totals[$currency] = ($totals[$currency] ?? 0) + $balance;
        }

        foreach ($totals as $currency => $amount) {
            $totals[$currency] = round($amount, 2);
        }

        return $totals;
    }

    /**
     * @return Collection<int, array{company: Company, currency: string, amount: float}>
     */
    public static function tenantsWithOutstanding(): Collection
    {
        return Company::query()
            ->billable()
            ->orderBy('name')
            ->get()
            ->map(function (Company $company) {
                $balance = self::outstandingBalance($company);
                if ($balance <= 0) {
                    return null;
                }

                return [
                    'company' => $company,
                    'currency' => $company->billingCurrency(),
                    'amount' => $balance,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Apply trial period when creating a tenant.
     */
    public static function applyTrialToCompanyData(array &$data, int $trialDays): void
    {
        if ($trialDays <= 0) {
            return;
        }

        $trialEnds = now()->addDays($trialDays);
        $data['trial_ends_at'] = $trialEnds;
        $data['billing_starts_at'] = $trialEnds->toDateString();

        if (empty($data['billing_due_date'])) {
            $data['billing_due_date'] = $trialEnds->toDateString();
        }
    }

    /**
     * @return array{
     *     currency: string,
     *     amount: float,
     *     interval: string|null,
     *     interval_label: string,
     *     description: string,
     *     period_start: string,
     *     period_end: string,
     *     due_date: string,
     *     line_items: list<array{description: string, quantity: float, unit_price: float}>
     * }
     */
    public static function draftInvoicePayload(Company $company, ?Carbon $periodStart = null): array
    {
        $interval = $company->billing_interval ?? 'monthly';
        $months = self::intervalMonths($interval);
        $currency = strtoupper($company->billingCurrency());
        $amount = round((float) ($company->billing_amount ?? 0), 2);

        $start = self::suggestedPeriodStart($company);
        if ($periodStart) {
            $start = $periodStart->copy()->startOfDay();
        }

        $end = $start->copy()->addMonths($months)->subDay();

        $description = sprintf(
            'FoodPOS subscription — %s (%s – %s)',
            self::intervalLabel($interval),
            $start->format('M j, Y'),
            $end->format('M j, Y')
        );

        $dueDate = $company->billing_due_date && $company->billing_due_date->greaterThan(now())
            ? $company->billing_due_date->toDateString()
            : now()->addDays((int) config('platform_billing.default_due_days', 14))->toDateString();

        return [
            'currency' => $currency,
            'amount' => $amount,
            'interval' => $interval,
            'interval_label' => self::intervalLabel($interval),
            'description' => $description,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'due_date' => $dueDate,
            'line_items' => [
                [
                    'description' => $description,
                    'quantity' => 1,
                    'unit_price' => $amount,
                ],
            ],
        ];
    }

    public static function suggestedPeriodStart(Company $company): Carbon
    {
        if ($company->billing_starts_at && $company->billing_starts_at->isFuture()) {
            return $company->billing_starts_at->copy()->startOfDay();
        }

        $lastInvoice = $company->platformInvoices()
            ->where('status', '!=', 'void')
            ->whereNotNull('period_end')
            ->orderByDesc('period_end')
            ->first();

        if ($lastInvoice?->period_end) {
            return $lastInvoice->period_end->copy()->addDay()->startOfDay();
        }

        if ($company->billing_starts_at) {
            return $company->billing_starts_at->copy()->startOfDay();
        }

        return now()->startOfDay();
    }

    public static function billingStatusLabel(Company $company): string
    {
        if ($company->demo) {
            return 'Demo';
        }

        if (self::isOnTrial($company)) {
            return 'Free trial';
        }

        if ((float) ($company->billing_amount ?? 0) <= 0) {
            return 'Complimentary';
        }

        if (! self::shouldChargeYet($company)) {
            return 'Trial / pre-billing';
        }

        $due = $company->billing_due_date;
        if ($due && $due->isPast()) {
            return 'Past due';
        }

        return 'Active billing';
    }
}
