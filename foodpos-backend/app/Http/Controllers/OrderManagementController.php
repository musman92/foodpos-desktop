<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderManagementNoteRequest;
use App\Http\Requests\StoreOrderRefundRequest;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Services\OrderDeleteService;
use App\Services\OrderRefundService;
use App\Support\ListingPerPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class OrderManagementController extends Controller
{
    public function __construct(
        protected OrderRefundService $orderRefundService,
        protected OrderDeleteService $orderDeleteService,
    ) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        abort_unless($user->hasAppPermission('order-management.index'), 403);

        $perPage = ListingPerPage::fromRequest($request);

        $query = Order::with(['branch', 'cashier'])
            ->withCount('refunds');

        $this->scopeOrdersForUser($query, $user);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('from') || $request->filled('to')) {
            $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;
            $from = $request->filled('from') ? (string) $request->from : '1970-01-01';
            $to = $request->filled('to') ? (string) $request->to : '2999-12-31';
            tz()->applyBusinessDateRange($query, $from, $to, $branchId);
        }

        if ($request->filled('order_number')) {
            $query->where('order_number', 'like', '%'.$request->order_number.'%');
        }

        $orders = $query->newestFirst()->paginate($perPage)->withQueryString();

        $branches = $this->branchesForUser($user);

        $canDelete = $user->hasAppPermission('order-management.destroy');

        return view('order-management.index', compact('orders', 'branches', 'perPage', 'canDelete'));
    }

    public function show(Order $order): View
    {
        abort_unless(Auth::user()->hasAppPermission('order-management.show'), 403);
        $this->authorizeOrder($order);

        $order->load([
            'items.menuItem',
            'items.deal',
            'branch',
            'cashier',
            'moneySource',
            'payments.moneySource',
            'refunds.lines.orderItem',
            'refunds.creator',
        ]);

        $allowsRefund = $this->orderAllowsRefund($order);

        return view('order-management.show', compact('order', 'allowsRefund'));
    }

    public function refund(StoreOrderRefundRequest $request, Order $order): RedirectResponse
    {
        $this->assertCanProcessRefunds(Auth::user());
        $this->authorizeOrder($order);

        if (! $this->orderAllowsRefund($order)) {
            return redirect()
                ->route('order-management.show', $order)
                ->with('error', 'This order cannot be refunded.');
        }

        $user = Auth::user();

        try {
            $this->orderRefundService->processRefund(
                $order,
                $request->input('lines', []),
                $request->input('notes'),
                $user->id
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Refund could not be processed. '.$e->getMessage());
        }

        return redirect()
            ->route('order-management.refunds.index')
            ->with('success', 'Refund processed successfully.');
    }

    public function refundsIndex(Request $request): View
    {
        $user = Auth::user();
        $this->assertCanProcessRefunds($user);

        $perPage = ListingPerPage::fromRequest($request);

        $query = OrderRefund::query()
            ->with(['order.branch', 'creator', 'lines.orderItem'])
            ->whereHas('order', function ($q) use ($user, $request) {
                $this->scopeOrdersForUser($q, $user);
                if ($request->filled('branch_id')) {
                    $q->where('branch_id', (int) $request->branch_id);
                }
                if ($request->filled('list_order')) {
                    $term = trim((string) $request->list_order);
                    $q->where('order_number', 'like', '%'.$term.'%');
                }
            })
            ->orderByDesc('created_at');

        if ($request->filled('from')) {
            $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;
            $query->where('created_at', '>=', tz()->localDateStartUtc($request->from, $branchId));
        }
        if ($request->filled('to')) {
            $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;
            $query->where('created_at', '<=', tz()->localDateEndUtc($request->to, $branchId));
        }

        $refunds = $query->paginate($perPage)->withQueryString();
        $branches = $this->branchesForUser($user);

        return view('order-management.refunds-index', compact('refunds', 'branches', 'perPage'));
    }

    public function refundsStart(Request $request): RedirectResponse
    {
        return $this->redirectToRefundProcessForOrderNumber($request, $request->get('order_number'));
    }

    public function refundsLookup(Request $request): RedirectResponse
    {
        return $this->redirectToRefundProcessForOrderNumber($request, $request->input('order_number'));
    }

    protected function redirectToRefundProcessForOrderNumber(Request $request, mixed $orderNumber): RedirectResponse
    {
        $user = Auth::user();
        $this->assertCanProcessRefunds($user);
        $term = trim((string) $orderNumber);
        if ($term === '') {
            return redirect()
                ->route('order-management.refunds.index')
                ->withInput()
                ->with('error', 'Enter an order number, then click Refund to open the adjustment form.');
        }

        $order = Order::query()
            ->where(function ($q) use ($term) {
                $q->where('order_number', 'like', '%'.$term.'%')
                    ->orWhere('order_number', $term);
            })
            ->orderByDesc('id');

        $this->scopeOrdersForUser($order, $user);
        $order = $order->first();

        if (! $order) {
            return redirect()
                ->route('order-management.refunds.index', array_filter([
                    'list_order' => $term,
                    'branch_id' => $request->get('branch_id'),
                    'from' => $request->get('from'),
                    'to' => $request->get('to'),
                ]))
                ->withInput()
                ->with('error', 'No order found for that number.');
        }

        $this->authorizeOrder($order);

        if (! $this->orderAllowsRefund($order)) {
            return redirect()
                ->route('order-management.refunds.index')
                ->withInput()
                ->with('error', 'This order cannot be refunded (still open, or nothing left to refund).');
        }

        return redirect()->route('order-management.refunds.process', $order);
    }

    public function refundProcess(Order $order): View
    {
        $this->assertCanProcessRefunds(Auth::user());
        $this->authorizeOrder($order);

        if (! $this->orderAllowsRefund($order)) {
            abort(403, 'This order cannot be refunded.');
        }

        $order->load([
            'items.menuItem',
            'items.deal',
            'branch',
        ]);

        $lineMeta = $order->items->map(function ($item) {
            $billable = max(0, (float) $item->quantity - (float) $item->quantity_refunded);
            $mi = $item->menuItem;
            $isDeal = (bool) $item->deal_id;
            $isRecipe = $mi && $mi->type === 'recipe';
            $unitValue = $billable > 0.0001 ? round(((float) $item->total_price) / $billable, 4) : 0.0;

            return [
                'order_item_id' => $item->id,
                'name' => $item->item_name,
                'billable' => round($billable, 2),
                'unit_value' => $unitValue,
                'is_deal' => $isDeal,
                'is_recipe' => $isRecipe,
            ];
        })->filter(fn (array $row) => $row['billable'] > 0.0001)->values()->map(function (array $row, int $idx) {
            $row['idx'] = $idx;

            return $row;
        })->all();

        $refundRows = collect($lineMeta)->map(function (array $row) {
            $bill = (float) $row['billable'];
            $row['selected'] = false;
            $row['qty'] = $bill < 1.0001 ? round($bill, 2) : 1.0;
            $row['line_notes'] = '';

            return $row;
        })->values()->all();

        $orderSubtotal = (float) $order->subtotal;
        $orderTax = (float) $order->tax_amount;
        $currencyMeta = $this->currencyMetaForView();

        return view('order-management.refund-process', compact('order', 'refundRows', 'orderSubtotal', 'orderTax', 'currencyMeta'));
    }

    /**
     * @return array{symbol: string, position: string, decimals: int}
     */
    protected function currencyMetaForView(): array
    {
        $config = get_company_config();

        return [
            'symbol' => get_currency_symbol($config['currency']),
            'position' => $config['currency_position'],
            'decimals' => (int) $config['decimal_points'],
        ];
    }

    protected function assertCanProcessRefunds(?\Illuminate\Contracts\Auth\Authenticatable $user): void
    {
        if (! $user instanceof \App\Models\User) {
            abort(403);
        }
        abort_unless($user->hasAppPermission('order-management.refund'), 403);
    }

    public function appendNote(StoreOrderManagementNoteRequest $request, Order $order): RedirectResponse
    {
        abort_unless(Auth::user()->hasAppPermission('order-management.append-note'), 403);
        $this->authorizeOrder($order);

        $user = Auth::user();
        $stamp = now()->format('Y-m-d H:i').' — '.$user->name.': ';
        $block = $stamp.$request->input('management_notes');
        $existing = trim((string) $order->management_notes);
        $order->management_notes = $existing === '' ? $block : $existing."\n\n".$block;
        $order->save();

        return redirect()
            ->route('order-management.show', $order)
            ->with('success', 'Note saved.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        abort_unless(Auth::user()->hasAppPermission('order-management.destroy'), 403);
        $this->authorizeOrder($order);

        try {
            $this->orderDeleteService->deleteOrder($order, (int) Auth::id());
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->with('error', 'Order could not be deleted. '.$e->getMessage());
        }

        return redirect()
            ->route('order-management.index')
            ->with('success', 'Order deleted and inventory/payments reversed.');
    }

    protected function authorizeOrder(Order $order): void
    {
        $user = Auth::user();
        if ($user->isSuperAdmin()) {
            return;
        }
        if ($user->company_id && (int) $order->company_id !== (int) $user->company_id) {
            abort(403, 'Unauthorized access to this order.');
        }
        if ($user->isCompanyAdmin()) {
            $currentBranchId = current_branch_id();
            if ($currentBranchId && (int) $order->branch_id === $currentBranchId) {
                return;
            }
            abort(403, 'Unauthorized access to this order.');
        }
        if ((int) $user->branch_id === (int) $order->branch_id) {
            return;
        }
        $allowed = $user->branches()->where('branches.id', $order->branch_id)->exists();
        if (! $allowed) {
            abort(403, 'Unauthorized access to this order.');
        }
    }

    private function branchesForUser($user)
    {
        if ($user->isSuperAdmin()) {
            return Branch::where('status', 'active')->orderBy('name')->get();
        }
        if ($user->isCompanyAdmin() && $user->company_id) {
            return Branch::where('company_id', $user->company_id)->where('status', 'active')->orderBy('name')->get();
        }

        return $user->branches()->where('status', 'active')->orderBy('name')->get();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Order>  $query
     */
    protected function scopeOrdersForUser($query, $user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }
        if ($user->company_id) {
            $query->where('company_id', (int) $user->company_id);
        }
        $currentBranchId = current_branch_id();
        if ($currentBranchId) {
            $query->where('branch_id', $currentBranchId);

            return;
        }
        if ($user->branch_id) {
            $query->where('branch_id', (int) $user->branch_id);

            return;
        }
        $ids = $user->branches()->pluck('branches.id')->all();
        if ($ids !== []) {
            $query->whereIn('branch_id', array_map('intval', $ids));
        }
    }

    protected function orderAllowsRefund(Order $order): bool
    {
        if ($order->status === 'open' && $order->payment_status === 'unpaid') {
            return false;
        }

        return $order->items->contains(function ($item) {
            return ((float) $item->quantity - (float) $item->quantity_refunded) > 0.0001;
        });
    }
}
