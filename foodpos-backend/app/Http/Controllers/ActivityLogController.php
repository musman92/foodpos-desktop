<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Company;
use App\Services\ActivityLogger;
use App\Services\CompanyConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->isSuperAdmin() || $user->isCompanyAdmin() || $user->hasAppPermission('activity-logs.index'), 403);

        $companyId = $user->isSuperAdmin()
            ? (int) ($request->get('company_id') ?: $user->company_id)
            : (int) $user->company_id;

        abort_unless($companyId > 0, 403);

        $company = Company::query()->findOrFail($companyId);

        $query = ActivityLog::query()
            ->with(['user:id,name', 'branch:id,name', 'shift:id,shift_date'])
            ->where('company_id', $companyId)
            ->orderByDesc('id');

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->get('branch_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->get('user_id'));
        }

        if ($request->filled('shift_id')) {
            $query->where('shift_id', (int) $request->get('shift_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->string('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->string('to'));
        }

        if ($request->filled('q')) {
            $q = '%'.$request->string('q').'%';
            $query->where(function ($inner) use ($q) {
                $inner->where('description', 'like', $q)
                    ->orWhere('action', 'like', $q);
            });
        }

        $logs = $query->paginate(50)->withQueryString();

        $branches = Branch::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $actions = ActivityLog::query()
            ->where('company_id', $companyId)
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $loggingEnabled = filter_var(
            ($company->settings ?? [])['activity_logging_enabled'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        return view('activity-logs.index', [
            'logs' => $logs,
            'branches' => $branches,
            'actions' => $actions,
            'loggingEnabled' => $loggingEnabled,
            'companyId' => $companyId,
            'company' => $company,
        ]);
    }

    public function toggle(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->isSuperAdmin() || $user->isCompanyAdmin(), 403);

        $companyId = $user->isSuperAdmin()
            ? (int) ($request->get('company_id') ?: $user->company_id)
            : (int) $user->company_id;

        $company = Company::query()->findOrFail($companyId);
        if (! $user->isSuperAdmin() && (int) $user->company_id !== (int) $company->id) {
            abort(403);
        }

        $enabled = $request->boolean('enabled');
        $settings = $company->settings ?? [];
        $settings['activity_logging_enabled'] = $enabled;
        $company->update(['settings' => $settings]);

        CompanyConfigService::warmSessionCaches($company->fresh());
        ActivityLogger::clearCache();

        return redirect()
            ->route('activity-logs.index', array_filter([
                'company_id' => $user->isSuperAdmin() ? $companyId : null,
            ]))
            ->with('success', $enabled
                ? 'Activity logging enabled. New actions will be recorded.'
                : 'Activity logging disabled.');
    }
}
