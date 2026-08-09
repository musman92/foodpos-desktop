<?php

namespace App\Support;

use App\Models\MoneySource;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TransactionsByMoneySourceReport
{
    private const REFERENCE_LABELS = [
        'sale' => 'Sale',
        'purchase' => 'Purchase',
        'refund' => 'Refund',
        'expense' => 'Expense',
        'customer_payment' => 'Customer payment',
        'transfer' => 'Transfer',
        'reconciliation' => 'Reconciliation',
        'adjustment' => 'Adjustment',
    ];

    /**
     * @return array{
     *   summary: array{count: int, total_in: float, total_out: float, net: float},
     *   by_source: Collection<int, array{money_source_id: ?int, money_source: string, total_in: float, total_out: float, net: float, count: int}>,
     *   rows: LengthAwarePaginator
     * }
     */
    public static function build(
        User $user,
        ?int $branchId,
        string $from,
        string $to,
        array $moneySourceIds = [],
        ?string $type = null,
        int $perPage = 50
    ): array {
        $moneySourceIds = array_values(array_unique(array_filter(
            array_map('intval', $moneySourceIds),
            static fn (int $id) => $id > 0
        )));

        $baseQuery = self::baseQuery($user, $branchId, $from, $to, $moneySourceIds, $type);

        $totalIn = (float) (clone $baseQuery)->where('type', 'in')->sum('amount');
        $totalOut = (float) (clone $baseQuery)->where('type', 'out')->sum('amount');
        $count = (int) (clone $baseQuery)->count();

        $sourceIds = (clone $baseQuery)
            ->whereNotNull('money_source_id')
            ->distinct()
            ->pluck('money_source_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $sourceNames = MoneySource::withoutGlobalScopes()
            ->whereIn('id', $sourceIds)
            ->pluck('name', 'id');

        $bySource = (clone $baseQuery)
            ->selectRaw('money_source_id, type, SUM(amount) as total, COUNT(*) as txn_count')
            ->groupBy('money_source_id', 'type')
            ->get()
            ->groupBy(fn ($row) => $row->money_source_id !== null ? (string) $row->money_source_id : 'null')
            ->map(function (Collection $group, string $key) use ($sourceNames) {
                $in = (float) $group->where('type', 'in')->sum('total');
                $out = (float) $group->where('type', 'out')->sum('total');
                $sourceCount = (int) $group->sum('txn_count');
                $sourceId = $key === 'null' ? null : (int) $key;

                return [
                    'money_source_id' => $sourceId,
                    'money_source' => $sourceId
                        ? (string) ($sourceNames[$sourceId] ?? 'Unknown')
                        : 'Unassigned',
                    'total_in' => round($in, 2),
                    'total_out' => round($out, 2),
                    'net' => round($in - $out, 2),
                    'count' => $sourceCount,
                ];
            })
            ->sortBy('money_source')
            ->values();

        $rows = (clone $baseQuery)
            ->with([
                'moneySource:id,name,type',
                'account:id,name',
                'branch:id,name',
                'creator:id,name',
            ])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Transaction $txn) => self::mapRow($txn));

        return [
            'summary' => [
                'count' => $count,
                'total_in' => round($totalIn, 2),
                'total_out' => round($totalOut, 2),
                'net' => round($totalIn - $totalOut, 2),
            ],
            'by_source' => $bySource,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{
     *   id: int,
     *   date: string,
     *   business_date: ?string,
     *   money_source: string,
     *   type: string,
     *   amount: float,
     *   reference_type: string,
     *   reference_label: string,
     *   account: string,
     *   branch: string,
     *   notes: string,
     *   created_by: string
     * }
     */
    protected static function mapRow(Transaction $txn): array
    {
        $ref = (string) ($txn->reference_type ?? '');

        return [
            'id' => (int) $txn->id,
            'date' => $txn->date?->format('Y-m-d') ?? '—',
            'business_date' => filled($txn->business_date)
                ? substr((string) $txn->business_date, 0, 10)
                : null,
            'money_source' => (string) ($txn->moneySource?->name ?? 'Unassigned'),
            'type' => (string) $txn->type,
            'amount' => round((float) $txn->amount, 2),
            'reference_type' => $ref,
            'reference_label' => self::REFERENCE_LABELS[$ref]
                ?? ($ref !== '' ? ucwords(str_replace('_', ' ', $ref)) : '—'),
            'account' => (string) ($txn->account?->name ?? '—'),
            'branch' => (string) ($txn->branch?->name ?? '—'),
            'notes' => (string) ($txn->notes ?? ''),
            'created_by' => (string) ($txn->creator?->name ?? '—'),
        ];
    }

    /**
     * @param  list<int>  $moneySourceIds
     * @return Builder<\App\Models\Transaction>
     */
    protected static function baseQuery(
        User $user,
        ?int $branchId,
        string $from,
        string $to,
        array $moneySourceIds,
        ?string $type
    ): Builder {
        $query = Transaction::query()->withoutGlobalScopes(['tenant', 'branch']);

        self::applyBranchScope($query, $user, $branchId);
        $query->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to);

        if ($moneySourceIds !== []) {
            $query->whereIn('money_source_id', $moneySourceIds);
        }

        if (in_array($type, ['in', 'out'], true)) {
            $query->where('type', $type);
        }

        return $query;
    }

    /**
     * @param  Builder<\App\Models\Transaction>  $query
     */
    protected static function applyBranchScope(Builder $query, User $user, ?int $branchId): void
    {
        if ($user->isSuperAdmin()) {
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            return;
        }

        if ($user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        if ($branchId) {
            $query->where('branch_id', $branchId);
        } elseif (! $user->isCompanyAdmin() && $user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }
    }
}
