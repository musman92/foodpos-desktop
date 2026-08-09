<?php

namespace App\Support;

use App\Models\MoneySourceFundMovement;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MoneySourceFundLedger
{
    /**
     * @return array{
     *     filters: array,
     *     rows: Collection<int, array>,
     *     summary: array{internal_total: float, owner_withdrawal_total: float, total: int}
     * }
     */
    public static function build(User $user, Request $request): array
    {
        $filters = self::filtersFromRequest($request);
        $rows = self::queryRows($user, $filters);

        return [
            'filters' => $filters,
            'rows' => $rows,
            'summary' => [
                'internal_total' => round((float) $rows->where('movement_kind', 'internal_transfer')->sum('amount'), 2),
                'owner_withdrawal_total' => round((float) $rows->where('movement_kind', 'owner_withdrawal')->sum('amount'), 2),
                'total' => $rows->count(),
            ],
        ];
    }

    /**
     * @return array{branch_id: ?int, from_money_source_id: ?int, to_money_source_id: ?int, movement_kind: string, from: ?string, to: ?string}
     */
    public static function filtersFromRequest(Request $request): array
    {
        return [
            'branch_id' => $request->filled('branch_id') ? (int) $request->input('branch_id') : null,
            'from_money_source_id' => $request->filled('from_money_source_id') ? (int) $request->input('from_money_source_id') : null,
            'to_money_source_id' => $request->filled('to_money_source_id') ? (int) $request->input('to_money_source_id') : null,
            'movement_kind' => in_array($request->input('movement_kind'), ['all', 'internal_transfer', 'owner_withdrawal'], true)
                ? $request->input('movement_kind')
                : 'all',
            'from' => $request->filled('from') ? (string) $request->input('from') : null,
            'to' => $request->filled('to') ? (string) $request->input('to') : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected static function queryRows(User $user, array $filters): Collection
    {
        $rows = collect();

        if ($filters['movement_kind'] === 'all' || $filters['movement_kind'] === 'internal_transfer') {
            $rows = $rows->concat(self::internalTransferRows($user, $filters));
        }

        if ($filters['movement_kind'] === 'all' || $filters['movement_kind'] === 'owner_withdrawal') {
            $rows = $rows->concat(self::ownerWithdrawalRows($user, $filters));
        }

        return $rows->sortByDesc(fn (array $row) => $row['sort_key'])->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected static function internalTransferRows(User $user, array $filters): Collection
    {
        $query = Transaction::query()
            ->with(['moneySource', 'creator', 'branch'])
            ->where('reference_type', 'transfer')
            ->where('type', 'out');

        self::applyBranchScope($query, $user, $filters['branch_id']);

        if ($user->company_id && ! $user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        if ($filters['from_money_source_id']) {
            $query->where('money_source_id', $filters['from_money_source_id']);
        }

        if ($filters['to_money_source_id']) {
            $query->where('ref_id', $filters['to_money_source_id']);
        }

        if ($filters['from']) {
            $query->whereDate('date', '>=', $filters['from']);
        }

        if ($filters['to']) {
            $query->whereDate('date', '<=', $filters['to']);
        }

        return $query->get()->map(function (Transaction $transaction) {
            $toSource = \App\Models\MoneySource::withoutGlobalScopes()->find($transaction->ref_id);

            return [
                'movement_kind' => 'internal_transfer',
                'movement_label' => 'Internal transfer',
                'date' => $transaction->date,
                'from_name' => $transaction->moneySource?->name ?? '—',
                'to_name' => $toSource?->name ?? '—',
                'amount' => (float) $transaction->amount,
                'branch_name' => $transaction->branch?->name ?? '—',
                'notes' => $transaction->notes,
                'created_by' => $transaction->creator?->name ?? '—',
                'sort_key' => $transaction->date->format('Y-m-d').'-t-'.$transaction->id,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    protected static function ownerWithdrawalRows(User $user, array $filters): Collection
    {
        $query = MoneySourceFundMovement::query()
            ->with(['fromMoneySource', 'toMoneySource', 'branch', 'creator'])
            ->where('movement_type', MoneySourceFundMovement::TYPE_OWNER_WITHDRAWAL);

        self::applyBranchScope($query, $user, $filters['branch_id']);

        if ($user->company_id && ! $user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        }

        if ($filters['from_money_source_id']) {
            $query->where('from_money_source_id', $filters['from_money_source_id']);
        }

        if ($filters['to_money_source_id']) {
            $query->where('to_money_source_id', $filters['to_money_source_id']);
        }

        if ($filters['from']) {
            $query->whereDate('movement_date', '>=', $filters['from']);
        }

        if ($filters['to']) {
            $query->whereDate('movement_date', '<=', $filters['to']);
        }

        return $query->get()->map(function (MoneySourceFundMovement $movement) {
            return [
                'movement_kind' => 'owner_withdrawal',
                'movement_label' => 'Owner withdrawal',
                'date' => $movement->movement_date,
                'from_name' => $movement->fromMoneySource?->name ?? '—',
                'to_name' => $movement->toMoneySource?->name ?? '—',
                'amount' => (float) $movement->amount,
                'branch_name' => $movement->branch?->name ?? '—',
                'notes' => $movement->notes,
                'created_by' => $movement->creator?->name ?? '—',
                'sort_key' => $movement->movement_date->format('Y-m-d').'-m-'.$movement->id,
            ];
        });
    }

    protected static function applyBranchScope(Builder $query, User $user, ?int $branchId): void
    {
        if ($branchId) {
            $query->where($query->getModel()->getTable().'.branch_id', $branchId);

            return;
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        if ($user->isCompanyAdmin() && $user->company_id) {
            return;
        }

        $branchIds = $user->branches()->pluck('branches.id')->all();
        if ($user->branch_id) {
            $branchIds[] = $user->branch_id;
        }
        $branchIds = array_values(array_unique(array_filter($branchIds)));

        if (! empty($branchIds)) {
            $query->whereIn($query->getModel()->getTable().'.branch_id', $branchIds);
        }
    }
}
