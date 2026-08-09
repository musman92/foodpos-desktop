<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMoneySourceRequest;
use App\Http\Requests\UpdateMoneySourceRequest;
use App\Models\Account;
use App\Models\Branch;
use App\Models\MoneySource;
use App\Models\Transaction;
use App\Services\OwnerWithdrawalService;
use App\Support\CurrentShift;
use App\Support\ListingPerPage;
use App\Support\MoneySourceFundLedger;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MoneySourceController extends Controller
{
    public function __construct(
        protected OwnerWithdrawalService $ownerWithdrawals,
    ) {}

    /**
     * Display a listing of money sources.
     */
    public function index(Request $request)
    {
        $perPage = ListingPerPage::fromRequest($request);
        $user = Auth::user();

        $moneySources = MoneySource::with('company', 'branches')
            ->operational()
            ->orderBy('type')
            ->orderBy('name')
            ->paginate($perPage);

        $systemSources = MoneySource::with('company')
            ->where('is_system', true)
            ->orderBy('name')
            ->get();

        $selectedBranchId = $request->get('branch_id')
            ?? current_branch_id()
            ?? $user->branch_id;

        $balances = [];
        foreach ($moneySources->concat($systemSources) as $moneySource) {
            if ($selectedBranchId) {
                $balances[$moneySource->id] = $moneySource->getCurrentBalance($selectedBranchId);
            }
        }

        return view('money-sources.index', compact('moneySources', 'systemSources', 'balances', 'selectedBranchId', 'perPage'));
    }

    /**
     * Show the form for creating a new money source.
     */
    public function create()
    {
        $user = Auth::user();
        $activeBranch = $this->resolveActiveBranch($user);

        return view('money-sources.create', compact('activeBranch'));
    }

    /**
     * Store a newly created money source.
     */
    public function store(StoreMoneySourceRequest $request)
    {
        $user = Auth::user();

        $moneySource = MoneySource::create([
            'company_id' => $user->company_id,
            'name' => $request->name,
            'type' => $request->type,
            'opening_balance' => $request->opening_balance,
            'active' => $request->boolean('active'),
            'exclude_from_dashboard_profit' => $request->boolean('exclude_from_dashboard_profit'),
        ]);

        $message = "Money source '{$moneySource->name}' created successfully.";
        $activeBranchId = $this->resolveActiveBranchId($user);

        if ($activeBranchId) {
            $moneySource->branches()->sync([$activeBranchId]);
            $branchName = Branch::find($activeBranchId)?->name;
            if ($branchName) {
                $message .= " Assigned to {$branchName}.";
            }
        } else {
            $message .= ' No active branch was selected — edit the money source to assign branches.';
        }

        return redirect()
            ->route('money-sources.index')
            ->with('success', $message);
    }

    /**
     * Display the specified money source.
     */
    public function show(Request $request, MoneySource $moneySource)
    {
        $user = Auth::user();
        $moneySource->load('company', 'branches');

        $selectedBranchId = $request->get('branch_id')
            ?? current_branch_id()
            ?? $user->branch_id;

        $currentBalance = null;
        $branchBalances = [];

        if ($selectedBranchId) {
            $currentBalance = $moneySource->getCurrentBalance($selectedBranchId);
        }

        foreach ($moneySource->branches as $branch) {
            $branchBalances[$branch->id] = [
                'branch' => $branch,
                'balance' => $moneySource->getCurrentBalance($branch->id),
            ];
        }

        $transactions = $moneySource->isOwnerWithdrawalBucket()
            ? collect()
            : $moneySource->getTransactionHistory($selectedBranchId);

        $fundMovements = $moneySource->isOwnerWithdrawalBucket()
            ? $moneySource->getFundMovementHistory($selectedBranchId)
            : collect();

        $availableBranches = $moneySource->branches;

        if ($request->ajax() || $request->has('ajax')) {
            $asOfDate = $request->get('as_of_date', now()->toDateString());
            $balance = $moneySource->getCurrentBalance($selectedBranchId, $asOfDate);

            return response()->json([
                'expected_balance' => $balance,
                'formatted_balance' => format_currency($balance),
            ]);
        }

        return view('money-sources.show', compact(
            'moneySource',
            'currentBalance',
            'branchBalances',
            'transactions',
            'fundMovements',
            'selectedBranchId',
            'availableBranches'
        ));
    }

    /**
     * Show the form for editing the specified money source.
     */
    public function edit(MoneySource $moneySource)
    {
        if ($moneySource->is_system) {
            abort(403, 'System money sources cannot be edited.');
        }

        $user = Auth::user();
        $branches = $this->availableBranches($user);
        $moneySource->load('branches');
        $selectedBranchIds = old('branch_ids', $moneySource->branches->pluck('id')->all());

        return view('money-sources.edit', compact('moneySource', 'branches', 'selectedBranchIds'));
    }

    /**
     * Update the specified money source.
     */
    public function update(UpdateMoneySourceRequest $request, MoneySource $moneySource)
    {
        if ($moneySource->is_system) {
            abort(403, 'System money sources cannot be edited.');
        }

        $moneySource->update([
            'name' => $request->name,
            'type' => $request->type,
            'active' => $request->boolean('active'),
            'exclude_from_dashboard_profit' => $request->boolean('exclude_from_dashboard_profit'),
        ]);

        $branchIds = collect($request->input('branch_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $allowedBranchIds = $this->availableBranches(Auth::user())->pluck('id')->all();
        $branchIds = array_values(array_intersect($branchIds, $allowedBranchIds));

        $moneySource->branches()->sync($branchIds);

        return redirect()
            ->route('money-sources.index')
            ->with('success', "Money source '{$moneySource->name}' updated successfully.");
    }

    /**
     * Remove the specified money source.
     */
    public function destroy(MoneySource $moneySource)
    {
        if ($moneySource->is_system) {
            return redirect()
                ->route('money-sources.index')
                ->with('error', 'System money sources cannot be deleted.');
        }

        // Check if money source is being used in any active shifts
        $hasActiveShifts = \App\Models\Shift::whereHas('moneySources', function ($query) use ($moneySource) {
            $query->where('money_sources.id', $moneySource->id);
        })
        ->where('status', 'active')
        ->exists();

        if ($hasActiveShifts) {
            return redirect()
                ->route('money-sources.index')
                ->with('error', "Money source '{$moneySource->name}' cannot be deleted as it is being used in an active shift.");
        }

        $name = $moneySource->name;
        $moneySource->delete();

        return redirect()
            ->route('money-sources.index')
            ->with('success', "Money source '{$name}' deleted successfully.");
    }

    /**
     * Standalone internal transfer form.
     */
    public function transferCreate(Request $request): View
    {
        $this->authorizeMoneySourceAction('money-sources.transfer');

        $user = Auth::user();
        $operationalSources = $this->operationalSourcesForCompany($user);
        $branches = $this->companyBranches($user);
        $prefillFromId = $request->integer('from') ?: null;

        return view('money-sources.transfer.create', compact('operationalSources', 'branches', 'prefillFromId'));
    }

    /**
     * Process standalone internal transfer.
     */
    public function transferStore(Request $request): RedirectResponse
    {
        $this->authorizeMoneySourceAction('money-sources.transfer');

        $user = Auth::user();

        $validated = $request->validate([
            'from_money_source_id' => 'required|exists:money_sources,id',
            'to_money_source_id' => 'required|exists:money_sources,id|different:from_money_source_id',
            'amount' => 'required|numeric|min:0.01',
            'branch_id' => 'required|exists:branches,id',
            'date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $fromSource = MoneySource::findOrFail($validated['from_money_source_id']);
        $toSource = MoneySource::findOrFail($validated['to_money_source_id']);

        try {
            $this->executeInternalTransfer(
                $user,
                $fromSource,
                $toSource,
                (float) $validated['amount'],
                (int) $validated['branch_id'],
                $validated['date'],
                $validated['notes'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('money-sources.reports')
            ->with('success', 'Successfully transferred '.format_currency($validated['amount'])." from {$fromSource->name} to {$toSource->name}.");
    }

    /**
     * Legacy per-source transfer URL → standalone form.
     */
    public function transfer(MoneySource $moneySource): RedirectResponse
    {
        if ($moneySource->is_system) {
            abort(403);
        }

        return redirect()->route('money-sources.transfer.create', ['from' => $moneySource->id]);
    }

    /**
     * Process transfer between money sources (legacy POST from old form).
     */
    public function processTransfer(Request $request, MoneySource $moneySource): RedirectResponse
    {
        $this->authorizeMoneySourceAction('money-sources.transfer');

        $user = Auth::user();

        $request->validate([
            'to_money_source_id' => 'required|exists:money_sources,id',
            'amount' => 'required|numeric|min:0.01',
            'branch_id' => 'required|exists:branches,id',
            'date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $toMoneySource = MoneySource::findOrFail($request->to_money_source_id);

        try {
            $this->executeInternalTransfer(
                $user,
                $moneySource,
                $toMoneySource,
                (float) $request->amount,
                (int) $request->branch_id,
                $request->date,
                $request->notes
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('money-sources.show', $moneySource)
            ->with('success', 'Successfully transferred '.format_currency($request->amount)." to {$toMoneySource->name}.");
    }

    public function ownerWithdrawalCreate(Request $request): View
    {
        $this->authorizeMoneySourceAction('money-sources.owner-withdrawal');

        $user = Auth::user();
        $operationalSources = $this->operationalSourcesForCompany($user);
        $branches = $this->companyBranches($user);
        $ownerBucket = MoneySource::ownerWithdrawalForCompany((int) $user->company_id);
        $prefillFromId = $request->integer('from') ?: null;

        return view('money-sources.owner-withdrawal.create', compact(
            'operationalSources',
            'branches',
            'ownerBucket',
            'prefillFromId'
        ));
    }

    public function operationalBalances(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'date' => 'nullable|date',
        ]);

        $branchId = (int) $validated['branch_id'];
        $date = $validated['date'] ?? now()->toDateString();

        $balances = $this->operationalSourcesForCompany($user)->mapWithKeys(function (MoneySource $source) use ($branchId, $date) {
            $amount = $source->getCurrentBalance($branchId, $date);

            return [
                $source->id => [
                    'amount' => $amount,
                    'formatted' => format_currency($amount),
                    'label' => $source->name.' ('.$source->type.') — '.format_currency($amount),
                ],
            ];
        });

        return response()->json(['balances' => $balances]);
    }

    public function ownerWithdrawalStore(Request $request): RedirectResponse
    {
        $this->authorizeMoneySourceAction('money-sources.owner-withdrawal');

        $user = Auth::user();

        $validated = $request->validate([
            'from_money_source_id' => 'required|exists:money_sources,id',
            'amount' => 'required|numeric|min:0.01',
            'branch_id' => 'required|exists:branches,id',
            'date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->ownerWithdrawals->record(
                $user,
                (int) $validated['from_money_source_id'],
                (float) $validated['amount'],
                (int) $validated['branch_id'],
                $validated['date'],
                $validated['notes'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()
            ->route('money-sources.reports', ['movement_kind' => 'owner_withdrawal'])
            ->with('success', 'Owner withdrawal of '.format_currency($validated['amount']).' recorded successfully.');
    }

    public function reports(Request $request): View
    {
        $this->authorizeMoneySourceAction('money-sources.reports');

        $user = Auth::user();
        $ledger = MoneySourceFundLedger::build($user, $request);
        $branches = $this->companyBranches($user);
        $operationalSources = $this->operationalSourcesForCompany($user);
        $ownerBucket = MoneySource::ownerWithdrawalForCompany((int) $user->company_id);

        return view('money-sources.reports.index', [
            'ledger' => $ledger,
            'branches' => $branches,
            'operationalSources' => $operationalSources,
            'ownerBucket' => $ownerBucket,
        ]);
    }

    /**
     * Show reconciliation form.
     */
    public function reconcile(MoneySource $moneySource)
    {
        if ($moneySource->is_system) {
            abort(403, 'System money sources cannot be reconciled.');
        }

        $user = Auth::user();
        
        // Get branches
        $branches = \App\Models\Branch::where('company_id', $user->company_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        
        return view('money-sources.reconcile', compact('moneySource', 'branches'));
    }

    /**
     * Process reconciliation.
     */
    public function processReconcile(Request $request, MoneySource $moneySource)
    {
        $user = Auth::user();
        
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'actual_balance' => 'required|numeric',
            'reconciliation_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);
        
        $branchId = $request->branch_id;
        $expectedBalance = $moneySource->getCurrentBalance($branchId, $request->reconciliation_date);
        $actualBalance = (float) $request->actual_balance;
        $difference = $actualBalance - $expectedBalance;
        
        // Get Reconciliation account
        $reconciliationAccount = \App\Models\Account::where('company_id', $user->company_id)
            ->where('name', 'Reconciliation')
            ->where('type', 'expense')
            ->first();
        
        if (!$reconciliationAccount) {
            $reconciliationAccount = \App\Models\Account::create([
                'company_id' => $user->company_id,
                'name' => 'Reconciliation',
                'type' => 'expense',
                'is_active' => true,
                'is_deletable' => true,
            ]);
        }
        
        // If there's a difference, create adjustment transaction
        if (abs($difference) > 0.01) {
            \App\Models\Transaction::create([
                'company_id' => $user->company_id,
                'branch_id' => $branchId,
                'account_id' => $reconciliationAccount->id,
                'amount' => abs($difference),
                'type' => $difference > 0 ? 'in' : 'out',
                'payment_method' => 'transfer',
                'money_source_id' => $moneySource->id,
                'reference_type' => 'reconciliation',
                'date' => $request->reconciliation_date,
                'created_by' => $user->id,
                'shift_id' => CurrentShift::id((int) $branchId, $user),
                'notes' => "Reconciliation adjustment. Expected: " . format_currency($expectedBalance) . ", Actual: " . format_currency($actualBalance) . ($request->notes ? " - {$request->notes}" : ''),
            ]);
        }
        
        $message = "Reconciliation completed. ";
        if (abs($difference) > 0.01) {
            $message .= "Difference: " . format_currency(abs($difference)) . " (" . ($difference > 0 ? 'surplus' : 'shortage') . ")";
        } else {
            $message .= "Balances match perfectly!";
        }
        
        return redirect()
            ->route('money-sources.show', $moneySource)
            ->with('success', $message);
    }

    protected function resolveActiveBranchId(User $user): ?int
    {
        $branchId = current_branch_id() ?? $user->branch_id;

        return $branchId ? (int) $branchId : null;
    }

    protected function resolveActiveBranch(User $user): ?Branch
    {
        $branchId = $this->resolveActiveBranchId($user);

        if (! $branchId) {
            return null;
        }

        return Branch::query()
            ->where('id', $branchId)
            ->where('status', 'active')
            ->when($user->company_id, fn ($q) => $q->where('company_id', $user->company_id))
            ->first();
    }

    protected function availableBranches(User $user): Collection
    {
        if ($user->isSuperAdmin()) {
            return Branch::where('status', 'active')->orderBy('name')->get();
        }

        if ($user->isCompanyAdmin() && $user->company_id) {
            return Branch::where('company_id', $user->company_id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get();
        }

        $branches = $user->branches()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        if ($branches->isEmpty() && $user->branch_id) {
            $branch = Branch::where('id', $user->branch_id)
                ->where('status', 'active')
                ->first();

            return $branch ? collect([$branch]) : collect();
        }

        return $branches;
    }

    protected function authorizeMoneySourceAction(string $permission): void
    {
        $user = Auth::user();
        if ($user->isSuperAdmin() || $user->isCompanyAdmin()) {
            return;
        }

        abort_unless($user->hasAppPermission($permission), 403);
    }

    protected function operationalSourcesForCompany(User $user): Collection
    {
        return MoneySource::operational()
            ->where('company_id', $user->company_id)
            ->where('active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    protected function companyBranches(User $user): Collection
    {
        return Branch::where('company_id', $user->company_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    protected function executeInternalTransfer(
        User $user,
        MoneySource $fromSource,
        MoneySource $toSource,
        float $amount,
        int $branchId,
        string $date,
        ?string $notes
    ): void {
        if ($fromSource->is_system || $toSource->is_system) {
            throw new \InvalidArgumentException('Invalid money source selection.');
        }

        if ((int) $fromSource->company_id !== (int) $user->company_id
            || (int) $toSource->company_id !== (int) $user->company_id) {
            throw new \InvalidArgumentException('Invalid money source selection.');
        }

        $available = $fromSource->getCurrentBalance($branchId, $date);
        if ($amount > $available + 0.0001) {
            throw new \InvalidArgumentException(
                'Insufficient balance in '.$fromSource->name.'. Available: '.format_currency($available)
            );
        }

        $transferAccount = Account::where('company_id', $user->company_id)
            ->where('name', 'Transfer')
            ->where('type', 'expense')
            ->first();

        if (! $transferAccount) {
            $transferAccount = Account::create([
                'company_id' => $user->company_id,
                'name' => 'Transfer',
                'type' => 'expense',
                'is_active' => true,
                'is_deletable' => true,
            ]);
        }

        DB::transaction(function () use ($user, $fromSource, $toSource, $amount, $branchId, $date, $notes, $transferAccount) {
            $shiftId = CurrentShift::id($branchId, $user);

            Transaction::create([
                'company_id' => $user->company_id,
                'branch_id' => $branchId,
                'account_id' => $transferAccount->id,
                'amount' => $amount,
                'type' => 'out',
                'payment_method' => 'transfer',
                'money_source_id' => $fromSource->id,
                'reference_type' => 'transfer',
                'date' => $date,
                'ref_id' => $toSource->id,
                'created_by' => $user->id,
                'shift_id' => $shiftId,
                'notes' => "Transfer to {$toSource->name}".($notes ? " - {$notes}" : ''),
            ]);

            Transaction::create([
                'company_id' => $user->company_id,
                'branch_id' => $branchId,
                'account_id' => $transferAccount->id,
                'amount' => $amount,
                'type' => 'in',
                'payment_method' => 'transfer',
                'money_source_id' => $toSource->id,
                'reference_type' => 'transfer',
                'date' => $date,
                'ref_id' => $fromSource->id,
                'created_by' => $user->id,
                'shift_id' => $shiftId,
                'notes' => "Transfer from {$fromSource->name}".($notes ? " - {$notes}" : ''),
            ]);
        });
    }
}
