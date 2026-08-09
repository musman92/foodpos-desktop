<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait HasTenantAndBranch
{
    use TenantScope, BranchScope;

    /**
     * Boot the combined trait.
     * Laravel will automatically call bootTenantScope and bootBranchScope
     * from the used traits, so we don't need to call them manually.
     */
    public static function bootHasTenantAndBranch(): void
    {
        // Additional boot logic can go here if needed
    }

    /**
     * Get all models without both scopes.
     */
    public static function withoutScopes(): Builder
    {
        return static::withoutGlobalScopes(['tenant', 'branch']);
    }

    /**
     * Scope to current user's company and branch.
     */
    public function scopeCurrentTenant(Builder $query): Builder
    {
        if (Auth::check() && Auth::user()->company_id) {
            $query->where('company_id', Auth::user()->company_id);
        }
        return $query;
    }

    public function scopeCurrentBranch(Builder $query): Builder
    {
        if (Auth::check() && Auth::user()->branch_id) {
            $query->where('branch_id', Auth::user()->branch_id);
        }
        return $query;
    }
}

