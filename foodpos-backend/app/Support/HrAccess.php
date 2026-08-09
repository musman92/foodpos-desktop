<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class HrAccess
{
    public static function branchesFor(User $user): Collection
    {
        $ids = BranchContext::allowedBranchIds($user);

        return Branch::withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public static function assertBranch(User $user, int $branchId): void
    {
        abort_unless(in_array($branchId, BranchContext::allowedBranchIds($user), true), 403);
    }

    public static function employeeUsers(User $user): Builder
    {
        $query = User::query()
            ->where('company_id', $user->company_id)
            ->whereHas('employeeProfile');

        if (! $user->isCompanyAdmin() && ! $user->isSuperAdmin()) {
            $ids = BranchContext::allowedBranchIds($user);
            $query->where(function ($branchQuery) use ($ids) {
                $branchQuery->whereIn('branch_id', $ids)
                    ->orWhereHas('branches', fn ($q) => $q->whereIn('branches.id', $ids));
            });
        }

        return $query;
    }
}
