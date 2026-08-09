<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FloorStaffEmployeeBackfillService
{
    /**
     * Create (or restore) employee profiles for existing waiter/rider users.
     *
     * @return array{
     *     candidates: int,
     *     created: int,
     *     restored: int,
     *     skipped: int,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function backfill(?int $companyId = null, bool $dryRun = false): array
    {
        $users = $this->candidateUsers($companyId);
        $rows = [];
        $created = 0;
        $restored = 0;
        $skipped = 0;

        $process = function () use ($users, $dryRun, &$rows, &$created, &$restored, &$skipped): void {
            foreach ($users as $user) {
                $existing = EmployeeProfile::withTrashed()
                    ->withoutGlobalScopes()
                    ->where('user_id', $user->id)
                    ->first();

                if ($existing && ! $existing->trashed()) {
                    $skipped++;
                    $rows[] = $this->row($user, 'skipped', 'Already has employee profile #'.$existing->id);
                    continue;
                }

                if ($existing && $existing->trashed()) {
                    if ($dryRun) {
                        $restored++;
                        $rows[] = $this->row($user, 'would_restore', 'Would restore soft-deleted profile #'.$existing->id);
                        continue;
                    }

                    $this->fillProfileDefaults($existing, $user);
                    $existing->restore();
                    $existing->save();
                    $this->ensureEmployeeNumber($existing);
                    $restored++;
                    $rows[] = $this->row($user, 'restored', 'Restored soft-deleted profile #'.$existing->id);
                    continue;
                }

                if ($dryRun) {
                    $created++;
                    $rows[] = $this->row($user, 'would_create', 'Would create employee profile linked to user #'.$user->id);
                    continue;
                }

                $profile = new EmployeeProfile;
                $this->fillProfileDefaults($profile, $user);
                $profile->save();
                $this->ensureEmployeeNumber($profile);
                $created++;
                $rows[] = $this->row($user, 'created', 'Created employee profile #'.$profile->id);
            }
        };

        if ($dryRun) {
            $process();
        } else {
            DB::transaction($process);
        }

        return [
            'candidates' => $users->count(),
            'created' => $created,
            'restored' => $restored,
            'skipped' => $skipped,
            'rows' => $rows,
        ];
    }

    /**
     * @return Collection<int, User>
     */
    public function candidateUsers(?int $companyId = null): Collection
    {
        return User::query()
            ->withoutGlobalScopes()
            ->whereIn('type', User::FLOOR_ACCOUNT_TYPES)
            ->whereNotNull('company_id')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('company_id')
            ->orderBy('name')
            ->get();
    }

    protected function fillProfileDefaults(EmployeeProfile $profile, User $user): void
    {
        $profile->fill([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'designation' => $profile->designation ?: $this->designationForType((string) $user->type),
            'employment_status' => $profile->employment_status ?: (
                $user->status === 'active' ? 'active' : 'suspended'
            ),
            'pay_frequency' => $profile->pay_frequency ?: 'monthly',
            'pay_rate' => $profile->pay_rate !== null
                ? $profile->pay_rate
                : (float) ($user->salary ?? 0),
            'standard_hours_per_day' => $profile->standard_hours_per_day ?: 8,
            'overtime_rate' => $profile->overtime_rate !== null ? $profile->overtime_rate : 0,
            'short_hours_policy' => $profile->short_hours_policy ?: 'full_day',
            'working_days' => $profile->working_days ?: EmployeeProfile::DEFAULT_WORKING_DAYS,
            'hire_date' => $profile->hire_date ?: ($user->created_at?->toDateString()),
            'notes' => $profile->notes ?: 'Backfilled from existing floor staff user (waiter/rider).',
        ]);
    }

    protected function ensureEmployeeNumber(EmployeeProfile $profile): void
    {
        if ($profile->employee_number) {
            return;
        }

        $profile->update([
            'employee_number' => sprintf('EMP-%05d', $profile->id),
        ]);
    }

    protected function designationForType(string $type): string
    {
        return match ($type) {
            'waiter' => 'Waiter',
            'rider' => 'Rider',
            'waiter_rider' => 'Waiter / Rider',
            default => 'Staff',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(User $user, string $action, string $detail): array
    {
        return [
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'name' => $user->name,
            'email' => $user->email,
            'type' => $user->type,
            'can_login' => $user->can_login ? 'yes' : 'no',
            'action' => $action,
            'detail' => $detail,
        ];
    }
}
