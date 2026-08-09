<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Account;
use App\Models\Branch;
use App\Models\MoneySource;
use App\Models\Transaction;
use App\Services\PaymentMethodService;
use App\Support\CurrentShift;
use App\Support\ListingPerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = ListingPerPage::fromRequest($request);
        $user = Auth::user();

        $query = Transaction::with(['account', 'branch', 'creator'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter: type (in / out)
        if ($request->filled('type') && in_array($request->type, ['in', 'out'])) {
            $query->where('type', $request->type);
        }

        // Filter: account
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        // Filter: reference (reference_type)
        if ($request->filled('reference')) {
            $query->where('reference_type', $request->reference);
        }

        // Filter: date range (transaction date)
        $from = $request->input('from');
        $to = $request->input('to');
        $from = is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : null;
        $to = is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : null;
        if ($from && $to && $to < $from) {
            [$from, $to] = [$to, $from];
        }
        if ($from) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('date', '<=', $to);
        }

        $transactions = $query->paginate($perPage)->withQueryString();

        // Accounts for filter dropdown
        $accounts = Account::where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        // Distinct reference_type values for filter dropdown (from current scope)
        $referenceTypes = Transaction::select('reference_type')
            ->whereNotNull('reference_type')
            ->where('reference_type', '!=', '')
            ->distinct()
            ->orderBy('reference_type')
            ->pluck('reference_type');

        return view('transactions.index', compact('transactions', 'accounts', 'referenceTypes', 'perPage'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = Auth::user();
        
        // Get active accounts
        $accounts = Account::where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();
        
        // Get branches (if user has access to multiple branches)
        $branches = Branch::where('company_id', $user->company_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        
        // Get money sources for manual selection
        $moneySources = \App\Models\MoneySource::forPayments()->where('company_id', $user->company_id)
            ->where('active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('transactions.create', compact('accounts', 'branches', 'moneySources'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransactionRequest $request)
    {
        $user = Auth::user();

        $branchId = $request->branch_id ?: $user->branch_id;

        // Get money source - use manual selection if provided, otherwise auto-detect
        $moneySource = null;
        if ($request->money_source_id) {
            // Manual selection
            $moneySource = \App\Models\MoneySource::forPayments()->where('company_id', $user->company_id)
                ->where('id', $request->money_source_id)
                ->where('active', true)
                ->first();
        }
        
        // Fallback to auto-detection if no manual selection
        if (!$moneySource && $branchId) {
            $moneySource = PaymentMethodService::getMoneySourceForPaymentMethod(
                $request->payment_method,
                $branchId,
                $user->company_id
            );
        }

        $transaction = Transaction::create([
            'company_id' => $user->company_id,
            'branch_id' => $branchId,
            'account_id' => $request->account_id,
            'amount' => $request->amount,
            'type' => $request->type,
            'payment_method' => $request->payment_method,
            'money_source_id' => $moneySource?->id,
            'reference_type' => $request->reference_type ?: null,
            'date' => $request->date,
            'ref_id' => $request->ref_id ?: null,
            'created_by' => $user->id,
            'shift_id' => CurrentShift::id($branchId, $user),
            'is_manual' => true,
            'notes' => $request->notes ?: null,
        ]);

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaction created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Transaction $transaction)
    {
        $transaction->load(['account', 'branch', 'creator', 'company']);

        return view('transactions.show', compact('transaction'));
    }

    public function edit(Transaction $transaction)
    {
        $user = Auth::user();
        $this->authorizeManualModification($transaction, 'transactions.update');

        $accounts = Account::withoutGlobalScopes()
            ->where('company_id', $transaction->company_id)
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();
        $branches = Branch::withoutGlobalScopes()
            ->where('company_id', $transaction->company_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $moneySources = MoneySource::withoutGlobalScopes()
            ->forPayments()
            ->where('company_id', $transaction->company_id)
            ->where('active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('transactions.edit', compact('transaction', 'accounts', 'branches', 'moneySources'));
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction)
    {
        $user = Auth::user();
        $branchId = $request->filled('branch_id')
            ? (int) $request->branch_id
            : ($transaction->branch_id ? (int) $transaction->branch_id : null);
        $moneySource = $this->resolveMoneySource(
            $request,
            $branchId,
            (int) $transaction->company_id
        );
        $branchChanged = $transaction->branch_id !== $branchId;

        $transaction->update([
            'branch_id' => $branchId,
            'account_id' => $request->account_id,
            'amount' => $request->amount,
            'type' => $request->type,
            'payment_method' => $request->payment_method,
            'money_source_id' => $moneySource?->id,
            'reference_type' => $request->reference_type ?: null,
            'date' => $request->date,
            'ref_id' => $request->ref_id ?: null,
            'shift_id' => $branchChanged && $branchId
                ? CurrentShift::id($branchId, $user)
                : $transaction->shift_id,
            'notes' => $request->notes ?: null,
        ]);

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaction updated successfully.');
    }

    public function destroy(Transaction $transaction)
    {
        $this->authorizeManualModification($transaction, 'transactions.destroy');
        $transaction->delete();

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaction deleted successfully.');
    }

    /**
     * Show the form for creating an adjustment transaction.
     */
    public function createAdjustment(Transaction $transaction)
    {
        $user = Auth::user();
        
        // Check authorization - only owner/admin can create adjustments
        if (!$user->isSuperAdmin() && !$user->isCompanyAdmin()) {
            abort(403, 'Only administrators can create adjustment transactions.');
        }
        
        // Get active accounts
        $accounts = Account::where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();
        
        // Get branches
        $branches = Branch::where('company_id', $user->company_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        
        // Get money sources
        $moneySources = \App\Models\MoneySource::forPayments()->where('company_id', $user->company_id)
            ->where('active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('transactions.adjustment', compact('transaction', 'accounts', 'branches', 'moneySources'));
    }

    /**
     * Store an adjustment transaction.
     */
    public function storeAdjustment(StoreTransactionRequest $request, Transaction $transaction)
    {
        $user = Auth::user();
        
        // Check authorization - only owner/admin can create adjustments
        if (!$user->isSuperAdmin() && !$user->isCompanyAdmin()) {
            abort(403, 'Only administrators can create adjustment transactions.');
        }

        $branchId = $request->branch_id ?: $transaction->branch_id;

        // Get money source - use manual selection if provided, otherwise auto-detect
        $moneySource = null;
        if ($request->money_source_id) {
            $moneySource = \App\Models\MoneySource::forPayments()->where('company_id', $user->company_id)
                ->where('id', $request->money_source_id)
                ->where('active', true)
                ->first();
        }
        
        if (!$moneySource && $branchId) {
            $moneySource = PaymentMethodService::getMoneySourceForPaymentMethod(
                $request->payment_method,
                $branchId,
                $user->company_id
            );
        }

        // Create adjustment transaction with reference to original
        $adjustment = Transaction::create([
            'company_id' => $user->company_id,
            'branch_id' => $branchId,
            'account_id' => $request->account_id,
            'amount' => $request->amount,
            'type' => $request->type,
            'payment_method' => $request->payment_method,
            'money_source_id' => $moneySource?->id,
            'reference_type' => 'adjustment',
            'date' => $request->date,
            'ref_id' => $transaction->id, // Reference to the original transaction
            'created_by' => $user->id,
            'notes' => $request->notes ?: 'Adjustment for transaction #' . $transaction->id,
        ]);

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Adjustment transaction created successfully.');
    }

    protected function authorizeManualModification(Transaction $transaction, string $permission): void
    {
        $user = Auth::user();

        abort_unless($user->hasAppPermission($permission), 403);
        abort_unless(
            $transaction->canBeModifiedBy($user),
            403,
            'Only transactions entered directly from the Transactions screen can be modified.'
        );
    }

    protected function resolveMoneySource(
        Request $request,
        ?int $branchId,
        int $companyId
    ): ?MoneySource {
        if ($request->money_source_id) {
            return MoneySource::withoutGlobalScopes()
                ->forPayments()
                ->where('company_id', $companyId)
                ->whereKey($request->money_source_id)
                ->where('active', true)
                ->first();
        }

        if (! $branchId) {
            return null;
        }

        return PaymentMethodService::getMoneySourceForPaymentMethod(
            $request->payment_method,
            $branchId,
            $companyId
        );
    }
}
