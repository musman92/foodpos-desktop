<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\PlatformInvoice;
use App\Services\PlatformInvoiceService;
use App\Support\TenantBilling;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class PlatformInvoiceController extends Controller
{
    public function __construct(private PlatformInvoiceService $invoices) {}

    public function index(Request $request): View
    {
        $this->authorizeSuperAdmin();

        $invoices = PlatformInvoice::with(['company', 'payments'])
            ->forBillableTenants()
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->integer('company_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $search = $request->string('q')->toString();
                $q->where(function ($builder) use ($search) {
                    $builder->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $companies = Company::billable()->orderBy('name')->get(['id', 'name', 'billing_currency', 'billing_amount', 'billing_interval']);

        return view('platform-invoices.index', [
            'invoices' => $invoices,
            'companies' => $companies,
            'statuses' => config('platform_billing.statuses', []),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeSuperAdmin();

        return view('platform-invoices.create', array_merge($this->formData(), [
            'preselectedCompanyId' => $request->integer('company_id') ?: null,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        [$data, $items] = $this->validatedPayload($request);
        $this->assertBillableCompany((int) $data['company_id']);

        $invoice = $this->invoices->create($data, $items);

        return redirect()
            ->route('platform-invoices.show', $invoice)
            ->with('success', 'Invoice created.');
    }

    public function show(PlatformInvoice $platformInvoice): View
    {
        $this->authorizeSuperAdmin();

        $platformInvoice->load(['company', 'items', 'payments.recorder', 'creator']);

        return view('platform-invoices.show', [
            'invoice' => $platformInvoice,
            'paymentMethods' => config('platform_billing.payment_methods', []),
        ]);
    }

    public function edit(PlatformInvoice $platformInvoice): View
    {
        $this->authorizeSuperAdmin();

        abort_unless($platformInvoice->isEditable(), 403, 'This invoice cannot be edited.');

        $platformInvoice->load(['company', 'items']);

        return view('platform-invoices.edit', array_merge(
            $this->formData(),
            ['invoice' => $platformInvoice]
        ));
    }

    public function update(Request $request, PlatformInvoice $platformInvoice): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        [$data, $items] = $this->validatedPayload($request);
        $this->assertBillableCompany((int) $data['company_id']);

        try {
            $this->invoices->update($platformInvoice, $data, $items);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform-invoices.show', $platformInvoice)
            ->with('success', 'Invoice updated.');
    }

    public function generateFromPlan(Request $request, Company $company): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        try {
            $invoice = $this->invoices->createFromBillingPlan($company, $request->boolean('mark_sent'));
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform-invoices.show', $invoice)
            ->with('success', 'Invoice generated from tenant billing plan.');
    }

    public function billingContext(Company $company): JsonResponse
    {
        $this->authorizeSuperAdmin();

        if ($company->demo) {
            return response()->json(['error' => 'Demo companies are excluded from billing.'], 422);
        }

        $periodStart = TenantBilling::suggestedPeriodStart($company);
        $draft = TenantBilling::draftInvoicePayload($company, $periodStart);

        return response()->json([
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'is_billable' => $company->isBillable(),
                'billing_enabled' => $company->billing_enabled,
                'billing_amount' => (float) ($company->billing_amount ?? 0),
                'billing_currency' => $company->billingCurrency(),
                'billing_interval' => $company->billing_interval,
                'billing_interval_label' => $company->billingIntervalLabel(),
                'billing_notes' => $company->billing_notes,
                'billing_due_date' => $company->billing_due_date?->toDateString(),
                'trial_ends_at' => $company->trial_ends_at?->toDateString(),
                'billing_starts_at' => $company->billing_starts_at?->toDateString(),
                'on_trial' => \App\Support\TenantBilling::isOnTrial($company),
                'should_charge_yet' => \App\Support\TenantBilling::shouldChargeYet($company),
                'outstanding_balance' => $company->outstandingBalance(),
            ],
            'draft' => $draft,
        ]);
    }

    public function send(PlatformInvoice $platformInvoice): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        try {
            $this->invoices->markSent($platformInvoice);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Invoice marked as sent.');
    }

    public function void(PlatformInvoice $platformInvoice): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        try {
            $this->invoices->void($platformInvoice);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform-invoices.index')
            ->with('success', 'Invoice voided.');
    }

    public function print(PlatformInvoice $platformInvoice): View
    {
        $this->authorizeSuperAdmin();

        $platformInvoice->load(['company', 'items', 'payments']);

        return view('platform-invoices.print', [
            'invoice' => $platformInvoice,
            'vendor' => config('platform_billing.vendor', []),
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<array{description: string, quantity: float, unit_price: float}>}
     */
    private function validatedPayload(Request $request): array
    {
        $intervalKeys = implode(',', array_keys(config('platform_billing.intervals', [])));

        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'currency' => ['nullable', 'string', 'size:3'],
            'billing_interval' => ['nullable', 'string', 'in:'.$intervalKeys],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'mark_sent' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $company = Company::findOrFail($validated['company_id']);

        $items = collect($validated['items'])
            ->map(fn (array $item) => [
                'description' => $item['description'],
                'quantity' => (float) $item['quantity'],
                'unit_price' => (float) $item['unit_price'],
            ])
            ->all();

        $data = [
            'company_id' => (int) $validated['company_id'],
            'issue_date' => $validated['issue_date'],
            'due_date' => $validated['due_date'],
            'period_start' => $validated['period_start'] ?? null,
            'period_end' => $validated['period_end'] ?? null,
            'currency' => strtoupper($validated['currency'] ?? $company->billingCurrency()),
            'billing_interval' => $validated['billing_interval'] ?? $company->billing_interval,
            'tax_amount' => round((float) ($validated['tax_amount'] ?? 0), 2),
            'notes' => $validated['notes'] ?? null,
            'mark_sent' => $request->boolean('mark_sent'),
        ];

        return [$data, $items];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'companies' => Company::billable()->orderBy('name')->get(),
            'billingIntervals' => TenantBilling::intervals(),
            'billingCurrencies' => config('platform_billing.currencies', ['USD']),
            'defaultDueDays' => (int) config('platform_billing.default_due_days', 14),
        ];
    }

    private function assertBillableCompany(int $companyId): void
    {
        $company = Company::findOrFail($companyId);

        abort_if($company->demo, 422, 'Demo companies cannot be invoiced.');
    }

    private function authorizeSuperAdmin(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403);
    }
}
