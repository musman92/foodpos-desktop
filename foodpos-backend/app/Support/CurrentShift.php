<?php

namespace App\Support;

use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Support\Facades\Auth;

final class CurrentShift
{
    public static function resolve(?int $branchId = null, ?User $user = null): ?Shift
    {
        $user = $user ?? Auth::user();
        if (! $user || $user->isSuperAdmin()) {
            return null;
        }

        $branchId = $branchId ?? BranchContext::currentBranchId($user);
        if (! $branchId) {
            return null;
        }

        return app(ShiftService::class)->getActiveShiftForUser($branchId, (int) $user->id);
    }

    public static function id(?int $branchId = null, ?User $user = null): ?int
    {
        return self::resolve($branchId, $user)?->id;
    }

    /**
     * Business calendar day for the user's active shift (shift_date), if any.
     */
    public static function businessDate(?int $branchId = null, ?User $user = null): ?string
    {
        $shift = self::resolve($branchId, $user);
        if (! $shift?->shift_date) {
            return null;
        }

        return $shift->shift_date instanceof \Carbon\CarbonInterface
            ? $shift->shift_date->format('Y-m-d')
            : substr((string) $shift->shift_date, 0, 10);
    }
}
