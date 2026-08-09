<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\User;
use App\Support\CurrentShift;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    /**
     * Cache enabled flags per company for the current request.
     *
     * @var array<int, bool>
     */
    private static array $enabledCache = [];

    public static function enabledForCompany(?int $companyId): bool
    {
        if (! $companyId) {
            return false;
        }

        if (array_key_exists($companyId, self::$enabledCache)) {
            return self::$enabledCache[$companyId];
        }

        $company = Company::query()->find($companyId);
        $enabled = filter_var(
            ($company?->settings ?? [])['activity_logging_enabled'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        return self::$enabledCache[$companyId] = $enabled;
    }

    public static function clearCache(): void
    {
        self::$enabledCache = [];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(
        string $action,
        ?int $companyId = null,
        ?string $description = null,
        array $properties = [],
        ?Model $subject = null,
        ?int $branchId = null,
        ?int $shiftId = null,
        ?User $user = null,
    ): ?ActivityLog {
        $user ??= Auth::user();
        $companyId ??= $user?->company_id ? (int) $user->company_id : null;

        if ($subject && method_exists($subject, 'getAttribute')) {
            $companyId ??= $subject->getAttribute('company_id')
                ? (int) $subject->getAttribute('company_id')
                : null;
            $branchId ??= $subject->getAttribute('branch_id')
                ? (int) $subject->getAttribute('branch_id')
                : null;
            $shiftId ??= $subject->getAttribute('shift_id')
                ? (int) $subject->getAttribute('shift_id')
                : null;
        }

        if (! self::enabledForCompany($companyId)) {
            return null;
        }

        $branchId ??= $user?->branch_id ? (int) $user->branch_id : null;
        $shiftId ??= CurrentShift::id($branchId, $user);

        try {
            return ActivityLog::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'user_id' => $user?->id,
                'shift_id' => $shiftId,
                'action' => $action,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'description' => $description,
                'properties' => $properties ?: null,
                'ip_address' => Request::ip(),
                'user_agent' => substr((string) Request::userAgent(), 0, 255) ?: null,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Activity log write failed', [
                'action' => $action,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    public static function changes(array $before, array $after): array
    {
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $diffBefore = [];
        $diffAfter = [];

        foreach ($keys as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ($old != $new) {
                $diffBefore[$key] = $old;
                $diffAfter[$key] = $new;
            }
        }

        return ['before' => $diffBefore, 'after' => $diffAfter];
    }
}
