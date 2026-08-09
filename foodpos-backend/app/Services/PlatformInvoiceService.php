<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PlatformInvoice;
use App\Models\PlatformInvoiceItem;
use App\Models\PlatformInvoicePayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PlatformInvoiceService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{description: string, quantity: float|int|string, unit_price: float|int|string}>  $items
     */
    public function create(array $data, array $items): PlatformInvoice
    {
        return DB::transaction(function () use ($data, $items) {
            $invoice = PlatformInvoice::create([
                'company_id' => $data['company_id'],
                'invoice_number' => PlatformInvoice::generateInvoiceNumber(),
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'currency' => strtoupper((string) ($data['currency'] ?? config('platform_billing.currency', 'USD'))),
                'billing_interval' => $data['billing_interval'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            $this->syncItems($invoice, $items);
            $this->recalculateTotals($invoice);

            if (! empty($data['mark_sent'])) {
                $this->markSent($invoice);
            }

            return $invoice->fresh(['company', 'items', 'payments']);
        });
    }

    public function createFromBillingPlan(Company $company, bool $markSent = false): PlatformInvoice
    {
        if (! $company->isBillable()) {
            throw new InvalidArgumentException('This company is not billable (demo or billing disabled).');
        }

        if (! \App\Support\TenantBilling::shouldChargeYet($company)) {
            $ends = $company->trial_ends_at?->format('M j, Y') ?? $company->billing_starts_at?->format('M j, Y');

            throw new InvalidArgumentException("Billing starts after the trial period ({$ends}).");
        }

        $amount = round((float) ($company->billing_amount ?? 0), 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Billing amount is zero — extend billing due date for complimentary access instead of generating an invoice.');
        }

        $payload = \App\Support\TenantBilling::draftInvoicePayload(
            $company,
            \App\Support\TenantBilling::suggestedPeriodStart($company)
        );

        return $this->create([
            'company_id' => $company->id,
            'issue_date' => now()->toDateString(),
            'due_date' => $payload['due_date'],
            'period_start' => $payload['period_start'],
            'period_end' => $payload['period_end'],
            'currency' => $payload['currency'],
            'billing_interval' => $payload['interval'],
            'tax_amount' => 0,
            'notes' => $company->billing_notes,
            'mark_sent' => $markSent,
        ], $payload['line_items']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{description: string, quantity: float|int|string, unit_price: float|int|string}>  $items
     */
    public function update(PlatformInvoice $invoice, array $data, array $items): PlatformInvoice
    {
        if (! $invoice->isEditable()) {
            throw new InvalidArgumentException('This invoice can no longer be edited.');
        }

        return DB::transaction(function () use ($invoice, $data, $items) {
            $invoice->update([
                'company_id' => $data['company_id'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'currency' => strtoupper((string) ($data['currency'] ?? $invoice->currency)),
                'billing_interval' => $data['billing_interval'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($invoice, $items);
            $this->recalculateTotals($invoice);

            return $invoice->fresh(['company', 'items', 'payments']);
        });
    }

    public function markSent(PlatformInvoice $invoice): PlatformInvoice
    {
        if ($invoice->status === 'void') {
            throw new InvalidArgumentException('Void invoices cannot be sent.');
        }

        $invoice->update([
            'sent_at' => now(),
            'status' => $invoice->status === 'draft' ? 'sent' : $invoice->status,
        ]);

        return $invoice;
    }

    public function void(PlatformInvoice $invoice): PlatformInvoice
    {
        if ($invoice->amount_paid > 0) {
            throw new InvalidArgumentException('Invoices with payments cannot be voided.');
        }

        $invoice->update(['status' => 'void']);

        return $invoice;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordPayment(PlatformInvoice $invoice, array $data): PlatformInvoicePayment
    {
        if (in_array($invoice->status, ['void', 'paid'], true)) {
            throw new InvalidArgumentException('Payments cannot be recorded on this invoice.');
        }

        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        if ($amount > $invoice->balance_due) {
            throw new InvalidArgumentException('Payment exceeds the invoice balance.');
        }

        return DB::transaction(function () use ($invoice, $data, $amount) {
            $payment = PlatformInvoicePayment::create([
                'platform_invoice_id' => $invoice->id,
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => Auth::id(),
            ]);

            $invoice->refresh();
            $invoice->load('payments');
            $invoice->refreshPaymentStatus();

            return $payment;
        });
    }

    /**
     * @param  list<array{description: string, quantity: float|int|string, unit_price: float|int|string}>  $items
     */
    private function syncItems(PlatformInvoice $invoice, array $items): void
    {
        $invoice->items()->delete();

        foreach ($items as $index => $item) {
            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '') {
                continue;
            }

            $quantity = max(0.01, round((float) ($item['quantity'] ?? 1), 2));
            $unitPrice = round((float) ($item['unit_price'] ?? 0), 2);
            $lineTotal = round($quantity * $unitPrice, 2);

            PlatformInvoiceItem::create([
                'platform_invoice_id' => $invoice->id,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'sort_order' => $index,
            ]);
        }
    }

    private function recalculateTotals(PlatformInvoice $invoice): void
    {
        $invoice->load('items');
        $subtotal = round((float) $invoice->items->sum('line_total'), 2);
        $tax = round((float) $invoice->tax_amount, 2);
        $total = round($subtotal + $tax, 2);

        $invoice->update([
            'subtotal' => $subtotal,
            'total_amount' => $total,
        ]);
    }
}
