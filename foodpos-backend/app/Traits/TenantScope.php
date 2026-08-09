<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait TenantScope
{
    /**
     * When true, tenant users also see platform-wide rows (company_id IS NULL).
     */
    protected static function tenantScopeIncludesGlobal(): bool
    {
        return false;
    }

    /**
     * Boot the tenant scope trait.
     * Automatically applies company_id filtering to all queries.
     */
    public static function bootTenantScope(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            // Super admins can see all companies
            if (Auth::check() && Auth::user()->type === 'super_admin') {
                return;
            }

            // If user has a company_id, scope to that company
            if (Auth::check() && Auth::user()->company_id) {
                $companyId = Auth::user()->company_id;
                if (static::tenantScopeIncludesGlobal()) {
                    $builder->where(function (Builder $query) use ($companyId) {
                        $query->where('company_id', $companyId)
                            ->orWhereNull('company_id');
                    });
                } else {
                    $builder->where('company_id', $companyId);
                }
            } else {
                // If no company_id, return empty result set for safety
                $builder->whereRaw('1 = 0');
            }
        });

        // Automatically set company_id when creating new records
        static::creating(function ($model) {
            if (! Auth::check() || ! Auth::user()->company_id) {
                return;
            }

            if ($model->company_id) {
                return;
            }

            if (static::tenantScopeIncludesGlobal() && Auth::user()->type === 'super_admin') {
                return;
            }

            $model->company_id = Auth::user()->company_id;
        });
    }

    /**
     * Get all models without tenant scope.
     */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope('tenant');
    }

    /**
     * Get all models for a specific tenant.
     */
    public static function forTenant(int $companyId): Builder
    {
        return static::withoutTenantScope()->where('company_id', $companyId);
    }
}

