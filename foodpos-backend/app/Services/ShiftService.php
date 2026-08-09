<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\MoneySource;
use App\Models\Shift;
use App\Models\Transaction;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShiftService
{
    /**
     * Start a new shift.
     *
     * @param  \DateTime|string  $shiftDate
     * @param  array  $moneySourceBalances  Array of ['money_source_id' => opening_balance]
     *
     * @throws \Exception
     */
    public function startShift(
        int $branchId,
        int $userId,
        $shiftDate,
        array $moneySourceBalances = [],
        ?string $notes = null
    ): Shift {
        return DB::transaction(function () use ($branchId, $userId, $shiftDate, $moneySourceBalances, $notes) {
            $activeShift = Shift::getActiveShiftForUser($branchId, $userId);
            if ($activeShift) {
                throw new \Exception('You already have an active shift for this branch. Please close it before starting a new one.');
            }

            // Get branch to get company_id
            $branch = Branch::withoutGlobalScopes()->findOrFail($branchId);

            // Create shift
            $shift = Shift::create([
                'company_id' => $branch->company_id,
                'branch_id' => $branchId,
                'opened_by' => $userId,
                'shift_date' => is_string($shiftDate) ? $shiftDate : $shiftDate->format('Y-m-d'),
                'opened_at' => now(),
                'status' => 'active',
                'opening_notes' => $notes,
                'expected_cash' => 0,
                'cash_difference' => 0,
            ]);

            // Attach money sources with opening balances
            if (! empty($moneySourceBalances)) {
                foreach ($moneySourceBalances as $moneySourceId => $openingBalance) {
                    $moneySource = MoneySource::withoutGlobalScopes()->find($moneySourceId);
                    if ($moneySource) {
                        $shift->moneySources()->attach($moneySourceId, [
                            'opening_balance' => $openingBalance,
                            'expected_balance' => $openingBalance,
                            'difference' => 0,
                        ]);
                    }
                }
            }

            Log::info('Shift started', [
                'shift_id' => $shift->id,
                'branch_id' => $branchId,
                'user_id' => $userId,
                'shift_date' => $shift->shift_date,
            ]);

            $shift->load('moneySources');
            ActivityLogger::log(
                'shift.opened',
                (int) $branch->company_id,
                'Shift opened',
                [
                    'shift_date' => is_string($shiftDate) ? $shiftDate : $shift->shift_date?->format('Y-m-d'),
                    'opening_notes' => $notes,
                    'money_sources' => $shift->moneySources->map(fn ($source) => [
                        'id' => $source->id,
                        'name' => $source->name,
                        'type' => $source->type,
                        'opening_balance' => (float) ($source->pivot->opening_balance ?? 0),
                    ])->values()->all(),
                ],
                $shift,
                $branchId,
                $shift->id
            );

            return $shift;
        });
    }

    /**
     * Close an active shift.
     *
     * @param  array  $moneySourceBalances  Array of ['money_source_id' => closing_balance]
     * @param  \DateTime|string|null  $closingDate
     *
     * @throws \Exception
     */
    public function closeShift(
        Shift $shift,
        int $userId,
        array $moneySourceBalances = [],
        $closingDate = null,
        ?string $notes = null
    ): Shift {
        return DB::transaction(function () use ($shift, $userId, $moneySourceBalances, $closingDate, $notes) {
            if ($shift->isClosed()) {
                throw new \Exception('This shift is already closed.');
            }

            $shift->loadMissing('moneySources');

            // Normalize request keys (HTTP / JSON may use string ids) and drop empty values
            $balancesByMoneySourceId = collect($moneySourceBalances ?? [])
                ->filter(static fn ($v) => $v !== null && $v !== '')
                ->mapWithKeys(static fn ($v, $k) => [(int) $k => (float) $v])
                ->all();

            // Calculate expected balances from transactions
            $expectedBalances = $this->calculateExpectedBalances($shift);

            // Update money sources with closing balances
            $totalExpectedCash = 0;
            $totalActualCash = 0;

            foreach ($shift->moneySources as $moneySource) {
                $msId = (int) $moneySource->id;
                $expectedBalance = (float) ($expectedBalances[$msId] ?? $moneySource->pivot->opening_balance ?? 0);
                // Always persist a closing amount: submitted value, or expected if the key was missing (edge cases / request quirks)
                $closingBalance = $balancesByMoneySourceId[$msId] ?? $expectedBalance;
                $difference = $closingBalance - $expectedBalance;

                $updated = DB::table('shift_money_sources')
                    ->where('shift_id', $shift->id)
                    ->where('money_source_id', $msId)
                    ->update([
                        'closing_balance' => $closingBalance,
                        'expected_balance' => $expectedBalance,
                        'difference' => $difference,
                        'updated_at' => now(),
                    ]);

                if ($updated === 0) {
                    throw new \Exception(
                        'Could not save closing balance for money source #'.$msId.'. It may not be linked to this shift.'
                    );
                }

                // Track cash totals for cash-type money sources (type is enum, case-insensitive)
                if (strtoupper((string) $moneySource->type) === 'CASH') {
                    $totalExpectedCash += $expectedBalance;
                    $totalActualCash += $closingBalance;
                }
            }

            // Update shift
            $shift->update([
                'closed_by' => $userId,
                'closed_at' => $closingDate ? (is_string($closingDate) ? $closingDate : $closingDate->format('Y-m-d H:i:s')) : now(),
                'status' => 'closed',
                'closing_notes' => $notes,
                'expected_cash' => $totalExpectedCash,
                'actual_cash' => $totalActualCash,
                'cash_difference' => $totalActualCash - $totalExpectedCash,
            ]);

            Log::info('Shift closed', [
                'shift_id' => $shift->id,
                'branch_id' => $shift->branch_id,
                'user_id' => $userId,
                'cash_difference' => $shift->cash_difference,
            ]);

            $closed = $shift->fresh(['moneySources']);
            ActivityLogger::log(
                'shift.closed',
                (int) $closed->company_id,
                'Shift closed',
                [
                    'shift_date' => $closed->shift_date?->format('Y-m-d'),
                    'expected_cash' => (float) $closed->expected_cash,
                    'actual_cash' => (float) $closed->actual_cash,
                    'cash_difference' => (float) $closed->cash_difference,
                    'closing_notes' => $notes,
                    'money_sources' => $closed->moneySources->map(fn ($source) => [
                        'id' => $source->id,
                        'name' => $source->name,
                        'type' => $source->type,
                        'opening_balance' => (float) ($source->pivot->opening_balance ?? 0),
                        'expected_balance' => (float) ($source->pivot->expected_balance ?? 0),
                        'closing_balance' => (float) ($source->pivot->closing_balance ?? 0),
                        'difference' => (float) ($source->pivot->difference ?? 0),
                    ])->values()->all(),
                ],
                $closed,
                (int) $closed->branch_id,
                $closed->id
            );

            return $closed;
        });
    }

    /**
     * Calculate expected balances for money sources based on transactions.
     */
    public function calculateExpectedBalances(Shift $shift): array
    {
        $expectedBalances = [];

        // Initialize with opening balances
        foreach ($shift->moneySources as $moneySource) {
            $expectedBalances[(int) $moneySource->id] = (float) ($moneySource->pivot->opening_balance ?? 0);
        }

        // Get all transactions for this shift period (ignore branch global scope so totals match the shift's branch
        // even when the closing user has a different primary branch_id or null branch_id).
        $shiftDate = $shift->shift_date->format('Y-m-d');

        $transactions = Transaction::withoutGlobalScope('branch')
            ->where(function ($query) use ($shift, $shiftDate) {
                $query->where('shift_id', $shift->id)
                    ->orWhere(function ($legacy) use ($shift, $shiftDate) {
                        $legacy->whereNull('shift_id')
                            ->where('branch_id', $shift->branch_id)
                            ->where('created_by', $shift->opened_by)
                            ->whereDate('date', $shiftDate)
                            ->where('created_at', '>=', $shift->opened_at)
                            ->when($shift->closed_at, function ($query) use ($shift) {
                                return $query->where('created_at', '<=', $shift->closed_at);
                            });
                    });
            })
            ->get();

        // Calculate based on transactions
        // Use money_source_id if available, otherwise fall back to payment method mapping
        $paymentMethodMap = [
            'cash' => 'CASH',
            'transfer' => 'BANK',
            'card' => 'BANK',
            'online' => 'APP',
        ];

        foreach ($transactions as $transaction) {
            $moneySource = null;

            // First try to use the money_source_id from transaction
            if ($transaction->money_source_id) {
                $moneySource = $shift->moneySources()
                    ->where('money_sources.id', $transaction->money_source_id)
                    ->first();
            }

            // If no money source found, fall back to payment method mapping
            if (! $moneySource) {
                $moneySourceType = $paymentMethodMap[$transaction->payment_method] ?? null;

                if ($moneySourceType) {
                    // Find money source by type for this shift
                    $moneySource = $shift->moneySources()
                        ->where('type', $moneySourceType)
                        ->first();
                }
            }

            if ($moneySource && array_key_exists((int) $moneySource->id, $expectedBalances)) {
                $mid = (int) $moneySource->id;
                if ($transaction->type === 'in') {
                    $expectedBalances[$mid] += (float) $transaction->amount;
                } else {
                    $expectedBalances[$mid] -= (float) $transaction->amount;
                }
            }
        }

        $fundMovements = \App\Models\MoneySourceFundMovement::query()
            ->where(function ($query) use ($shift) {
                $query->where('shift_id', $shift->id)
                    ->orWhere(function ($legacy) use ($shift) {
                        $legacy->whereNull('shift_id')
                            ->where('branch_id', $shift->branch_id)
                            ->where('created_by', $shift->opened_by)
                            ->whereDate('movement_date', $shift->shift_date->format('Y-m-d'))
                            ->where('created_at', '>=', $shift->opened_at)
                            ->when($shift->closed_at, function ($query) use ($shift) {
                                return $query->where('created_at', '<=', $shift->closed_at);
                            });
                    });
            })
            ->get();

        foreach ($fundMovements as $movement) {
            $fromId = (int) $movement->from_money_source_id;
            if (array_key_exists($fromId, $expectedBalances)) {
                $expectedBalances[$fromId] -= (float) $movement->amount;
            }
        }

        return $expectedBalances;
    }

    /**
     * Check if there's an active shift for a branch.
     */
    public function hasActiveShift(int $branchId, ?int $userId = null): bool
    {
        return $this->getActiveShift($branchId, $userId) !== null;
    }

    public function getActiveShift(int $branchId, ?int $userId = null): ?Shift
    {
        if ($userId) {
            return Shift::getActiveShiftForUser($branchId, $userId);
        }

        return Shift::getActiveShift($branchId);
    }

    public function getActiveShiftForUser(int $branchId, int $userId): ?Shift
    {
        return Shift::getActiveShiftForUser($branchId, $userId);
    }

    public function userCanCloseShift(Shift $shift, int $userId, bool $isCompanyAdmin = false): bool
    {
        if ($isCompanyAdmin) {
            return true;
        }

        return $shift->isOwnedBy($userId);
    }
}
