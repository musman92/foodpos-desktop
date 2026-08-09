<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class BranchContext
{
    /**
     * Effective branch for tenant operational data (topbar selection, user default, or first active branch).
     */
    public static function currentBranchId(?User $user = null): ?int
    {
        $user = $user ?? Auth::user();
        if (! $user || $user->isSuperAdmin()) {
            return null;
        }

        if (! $user->company_id) {
            return $user->branch_id ? (int) $user->branch_id : null;
        }

        $companyId = (int) $user->company_id;
        $allowedIds = self::allowedBranchIds($user);

        if (Session::has('current_branch_id')) {
            $sessionId = (int) Session::get('current_branch_id');
            if (in_array($sessionId, $allowedIds, true)) {
                return $sessionId;
            }
        }

        if ($user->branch_id) {
            $primaryId = (int) $user->branch_id;
            if (in_array($primaryId, $allowedIds, true)) {
                return $primaryId;
            }
        }

        return $allowedIds[0] ?? null;
    }

    /**
     * Persist session branch and sync Auth user branch_id for the current request.
     */
    public static function syncRequestContext(?User $user = null): ?int
    {
        $user = $user ?? Auth::user();
        if (! $user || $user->isSuperAdmin()) {
            return null;
        }

        $branchId = self::currentBranchId($user);
        if (! $branchId) {
            return null;
        }

        if ($user->canAccessMultipleBranches()) {
            Session::put('current_branch_id', $branchId);
        }

        $user->branch_id = $branchId;

        return $branchId;
    }

    /**
     * Branch IDs the user may access within their company.
     *
     * @return list<int>
     */
    public static function allowedBranchIds(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return Branch::withoutGlobalScopes(['tenant', 'branch'])
                ->where('status', 'active')
                ->orderBy('name')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if (! $user->company_id) {
            return $user->branch_id ? [(int) $user->branch_id] : [];
        }

        if ($user->isCompanyAdmin()) {
            return Branch::withoutGlobalScopes(['tenant', 'branch'])
                ->where('company_id', (int) $user->company_id)
                ->where('status', 'active')
                ->orderBy('name')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $ids = $user->branches()
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('branches.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids !== []) {
            return $ids;
        }

        if ($user->branch_id) {
            $branchId = (int) $user->branch_id;
            if (self::branchBelongsToCompany($branchId, (int) $user->company_id)) {
                return [$branchId];
            }
        }

        return [];
    }

    public static function branchBelongsToCompany(int $branchId, int $companyId): bool
    {
        return Branch::withoutGlobalScopes(['tenant', 'branch'])
            ->where('id', $branchId)
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->exists();
    }
}
