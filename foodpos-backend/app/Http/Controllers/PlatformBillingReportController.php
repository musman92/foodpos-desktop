<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\PlatformInvoice;
use App\Support\PlatformBillingMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PlatformBillingReportController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403);

        $defaultStart = now()->startOfMonth()->toDateString();
        $defaultEnd = now()->endOfMonth()->toDateString();

        $startDate = $request->input('start_date', $defaultStart);
        $endDate = $request->input('end_date', $defaultEnd);
        $companyId = $request->filled('company_id') ? $request->integer('company_id') : null;

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = $defaultStart;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $endDate = $defaultEnd;
        }
        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $summary = PlatformBillingMetrics::report($companyId, $startDate, $endDate);
        $monthlySeries = PlatformBillingMetrics::monthlySeries(6);

        $invoices = PlatformInvoice::with(['company', 'payments'])
            ->forBillableTenants()
            ->where('status', '!=', 'void')
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderByDesc('issue_date')
            ->paginate(25)
            ->withQueryString();

        return view('platform-billing.report', [
            'companies' => Company::billable()->orderBy('name')->get(['id', 'name']),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'companyId' => $companyId,
            'summary' => $summary,
            'monthlySeries' => $monthlySeries,
            'invoices' => $invoices,
        ]);
    }
}
