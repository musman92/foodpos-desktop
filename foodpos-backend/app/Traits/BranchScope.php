<?php

namespace App\Traits;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BranchScope
{
    /**
     * Boot the branch scope trait.
     * Automatically applies branch_id filtering to all queries.
     */
    public static function bootBranchScope(): void
    {
        static::addGlobalScope('branch', function (Builder $builder) {
            if (! Auth::check()) {
                return;
            }

            $user = Auth::user();
            if ($user->isSuperAdmin()) {
                return;
            }

            $branchId = BranchContext::currentBranchId($user);
            if ($branchId) {
                $builder->where($builder->getModel()->getTable().'.branch_id', $branchId);
            } else {
                $builder->whereRaw('1 = 0');
            }
        });

        static::creating(function ($model) {
            if (Auth::check() && ! $model->branch_id) {
                $branchId = BranchContext::currentBranchId();
                if ($branchId) {
                    $model->branch_id = $branchId;
                }
            }
        });
    }

    /**
     * Get all models without branch scope.
     */
    public static function withoutBranchScope(): Builder
    {
        return static::withoutGlobalScope('branch');
    }

    /**
     * Get all models for a specific branch.
     */
    public static function forBranch(int $branchId): Builder
    {
        return static::withoutBranchScope()->where('branch_id', $branchId);
    }
}
