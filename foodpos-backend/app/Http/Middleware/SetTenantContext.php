<?php

namespace App\Http\Middleware;

use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    /**
     * Handle an incoming request.
     * This middleware ensures that the authenticated user's company and branch
     * context is properly set for multi-tenant data isolation.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('login') || $request->is('logout')) {
            return $next($request);
        }

        if (Auth::check()) {
            $user = Auth::user();

            if ($user->isSuperUser()) {
                setPermissionsTeamId(null);
            } else {
                setPermissionsTeamId($user->company_id);
            }

            if ($user->isSuperAdmin()) {
                return $next($request);
            }

            if (! $user->company_id) {
                abort(403, 'User must be associated with a company.');
            }

            $branchId = BranchContext::syncRequestContext($user);

            if ($request->filled('branch_id')) {
                $requestedBranchId = (int) $request->input('branch_id');
                if ($requestedBranchId > 0) {
                    $allowed = in_array($requestedBranchId, BranchContext::allowedBranchIds($user), true);
                    if ($allowed) {
                        $branchId = $requestedBranchId;
                        $user->branch_id = $branchId;
                    }
                }
            }

            if ($user->company_id && $user->company) {
                if (! $request->session()->has('company_config')) {
                    \App\Services\CompanyConfigService::warmSessionCaches($user->company);
                } elseif (! $request->session()->has(\App\Services\CompanyReceiptBrandingService::SESSION_KEY)) {
                    \App\Services\CompanyReceiptBrandingService::warmSession($user->company);
                }
            }

            app(\App\Services\TimezoneService::class)->applyRuntimeTimezone($branchId);
        }

        return $next($request);
    }
}
