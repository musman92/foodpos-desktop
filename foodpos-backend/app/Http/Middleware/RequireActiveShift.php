<?php

namespace App\Http\Middleware;

use App\Services\ShiftService;
use App\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveShift
{
    protected ShiftService $shiftService;

    public function __construct(ShiftService $shiftService)
    {
        $this->shiftService = $shiftService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Super admins bypass shift requirement
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Get branch ID from request
        $branchId = $request->input('branch_id')
            ?? $request->route('branch_id')
            ?? $request->route('branch')
            ?? BranchContext::currentBranchId($user);

        if (! $branchId) {
            return $next($request);
        }

        if (! $this->shiftService->hasActiveShift((int) $branchId, (int) $user->id)) {
            return redirect()
                ->route('shifts.create', ['branch_id' => $branchId])
                ->with('error', 'You must start your shift before performing transactions.');
        }

        return $next($request);
    }
}
