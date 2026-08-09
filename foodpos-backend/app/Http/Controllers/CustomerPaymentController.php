<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Support\ListingPerPage;
use App\Models\MoneySource;
use App\Models\Order;
use App\Services\CustomerPaymentService;
use App\Support\PartyBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class CustomerPaymentController extends Controller
{
    public function __construct(
        protected CustomerPaymentService $customerPaymentService
    ) {}

    public function index(Request $request): View
    {
        $perPage = ListingPerPage::fromRequest($request);
        $this->authorizeModule('customer-payments.index');

        $payments = CustomerPayment::with(['customer', 'branch', 'moneySource', 'creator'])
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->customer_id))
            ->orderByDesc('payment_date')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('customer-payments.index', compact('payments', 'perPage'));
    }

    public function create(Request $request): View
    {
        $this->authorizeModule('customer-payments.store');

        return $this->paymentForm($request, CustomerPayment::KIND_COLLECTION, 'customer-payments.create', 'Receive payment');
    }

    public function createAdvance(Request $request): View
    {
        $this->authorizeModule('customer-payments.store');

        return $this->paymentForm($request, CustomerPayment::KIND_ADVANCE, 'customer-payments.create', 'Receive advance');
    }

    public function customerContext(Request $request): JsonResponse
    {
        $this->authorizeModule('customer-payments.store');

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        $user = Auth::user();
        $customer = Customer::findOrFail($validated['customer_id']);

        if ($customer->company_id !== $user->company_id && ! $user->isSuperAdmin()) {
            abort(403);
        }

        $balance = round((float) $customer->balance, 2);

        return response()->json([
            'balance' => $balance,
            'credit_available' => PartyBalance::customerCreditAvailable($balance),
            'partial_orders' => $this->partialOrdersPayload($customer),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeModule('customer-payments.store');

        return $this->storePayment($request, CustomerPayment::KIND_COLLECTION);
    }

    public function storeAdvance(Request $request): RedirectResponse
    {
        $this->authorizeModule('customer-payments.store');

        return $this->storePayment($request, CustomerPayment::KIND_ADVANCE);
    }

    public function show(CustomerPayment $customerPayment): View
    {
        $this->authorizeModule('customer-payments.index');

        $customerPayment->load(['customer', 'branch', 'moneySource', 'creator']);

        return view('customer-payments.show', compact('customerPayment'));
    }

    public function destroy(CustomerPayment $customerPayment): RedirectResponse
    {
        $this->authorizeModule('customer-payments.destroy');

        $user = Auth::user();
        if ($customerPayment->company_id !== $user->company_id && ! $user->isSuperAdmin()) {
            abort(403);
        }

        try {
            $this->customerPaymentService->deletePayment($customerPayment);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()
            ->route('customer-payments.index')
            ->with('success', 'Customer payment deleted and balances restored.');
    }

    protected function storePayment(Request $request, string $kind): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'money_source_id' => ['required', 'exists:money_sources,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);

        if ($customer->company_id !== $user->company_id && ! $user->isSuperAdmin()) {
            abort(403);
        }

        $branchId = $validated['branch_id'] ?? null;
        $discountAmount = (float) ($validated['discount_amount'] ?? 0);

        try {
            if ($kind === CustomerPayment::KIND_ADVANCE) {
                $payment = $this->customerPaymentService->receiveAdvance(
                    $customer,
                    (float) $validated['amount'],
                    (int) $validated['money_source_id'],
                    $user,
                    $branchId ? (int) $branchId : null,
                    $validated['payment_date'],
                    $validated['notes'] ?? null
                );
            } else {
                $payment = $this->customerPaymentService->receivePayment(
                    $customer,
                    (float) $validated['amount'],
                    (int) $validated['money_source_id'],
                    $user,
                    $branchId ? (int) $branchId : null,
                    $validated['payment_date'],
                    $validated['notes'] ?? null,
                    $discountAmount
                );
            }
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('customer-payments.show', $payment)
            ->with('success', $kind === CustomerPayment::KIND_ADVANCE
                ? 'Customer advance recorded successfully.'
                : 'Customer payment recorded successfully.');
    }

    protected function paymentForm(Request $request, string $kind, string $view, string $title): View
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $customers = Customer::query()
            ->where('is_active', true)
            ->when($request->boolean('with_balance'), fn ($q) => $q->where('balance', '>', 0))
            ->orderBy('name')
            ->get();

        $branches = Branch::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $selectedCustomerId = old('customer_id', $request->get('customer_id'));
        $selectedBranchId = old('branch_id', $request->get('branch_id', $user->branch_id));
        $selectedCustomer = $selectedCustomerId
            ? Customer::query()->where('id', (int) $selectedCustomerId)->first()
            : null;

        $moneySources = $this->moneySourcesForBranch($companyId, $selectedBranchId ? (int) $selectedBranchId : null);

        $customerOptions = $customers->map(function (Customer $c) {
            $balance = round((float) $c->balance, 2);
            $suffix = $balance > 0
                ? ' — owes '.format_currency($balance)
                : ($balance < 0 ? ' — credit '.format_currency(abs($balance)) : '');

            return [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone ?? '',
                'email' => $c->email ?? '',
                'balance' => $balance,
                'credit_available' => PartyBalance::customerCreditAvailable($balance),
                'label' => $c->name.$suffix,
            ];
        })->values();

        $initialPartialOrders = $selectedCustomer && $kind === CustomerPayment::KIND_COLLECTION
            ? $this->partialOrdersPayload($selectedCustomer)
            : [];

        return view($view, compact(
            'customerOptions',
            'branches',
            'moneySources',
            'selectedCustomerId',
            'selectedBranchId',
            'selectedCustomer',
            'initialPartialOrders',
            'kind',
            'title'
        ));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, MoneySource>
     */
    private function moneySourcesForBranch(int $companyId, ?int $branchId)
    {
        $query = MoneySource::forPayments()
            ->where('company_id', $companyId)
            ->where('active', true);

        if ($branchId) {
            $query->where(function ($q) use ($branchId) {
                $q->whereHas('branches', fn ($b) => $b->where('branches.id', $branchId))
                    ->orWhereDoesntHave('branches');
            });
        }

        return $query->orderBy('type')->orderBy('name')->get();
    }

    /**
     * @return list<array{order_number: string, owed: string}>
     */
    private function partialOrdersPayload(Customer $customer): array
    {
        return Order::query()
            ->where('customer_id', $customer->id)
            ->withOutstandingPayment()
            ->orderBy('id')
            ->get(['order_number', 'total_amount', 'paid_amount'])
            ->map(fn (Order $order) => [
                'order_number' => $order->order_number,
                'owed' => format_currency(max(0, (float) $order->total_amount - (float) $order->paid_amount)),
            ])
            ->values()
            ->all();
    }

    private function authorizeModule(string $permission): void
    {
        abort_unless(Auth::user()->hasAppPermission($permission), 403);
    }
}
