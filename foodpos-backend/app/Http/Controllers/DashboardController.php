<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\MoneySource;
use App\Services\ShiftService;
use App\Support\AccountsOutstandingReport;
use App\Support\DashboardMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        private ShiftService $shiftService,
    ) {}
    /**
     * Show the application dashboard.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Super user (no company, no branch): show super admin dashboard
        if ($user->isSuperUser()) {
            return $this->superDashboard();
        }

        if (! $user->hasAppPermission('dashboard.index')) {
            abort(403, 'You do not have permission to view the dashboard.');
        }
        
        // Get available branches
        $availableBranches = $this->getAvailableBranches($user);
        
        // Operational dashboard follows topbar branch selection
        $selectedBranchId = current_branch_id() ?? $user->branch_id;
        
        // Ensure selected branch is valid and accessible
        $selectedBranch = $availableBranches->firstWhere('id', $selectedBranchId) 
            ?? $availableBranches->first();

        $branchToday = $selectedBranch ? local_now($selectedBranch->id) : local_now();
        $defaultStart = $branchToday->copy()->startOfMonth()->toDateString();
        $defaultEnd = $branchToday->copy()->endOfMonth()->toDateString();

        $startDate = $request->input('start_date', $defaultStart);
        $endDate = $request->input('end_date', $defaultEnd);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = $defaultStart;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $endDate = $defaultEnd;
        }
        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }
        
        if (!$selectedBranch) {
            return view('dashboard', [
                'user' => $user,
                'availableBranches' => collect(),
                'selectedBranch' => null,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'showShiftReminder' => false,
                'shiftReminderBranchId' => null,
                'todayStats' => null,
                'periodStats' => null,
                'revenueChartDaily' => null,
                'expensesChartDaily' => null,
                'orderTypeBreakdown' => null,
                'operationalComparison' => null,
                'customerReceivables' => null,
                'supplierPayables' => null,
                'topFoodItems' => null,
                'lowStockItems' => null,
                'moneySourceBalances' => [],
            ]);
        }

        [$showShiftReminder, $shiftReminderBranchId] = $this->resolveShiftReminder($user, $selectedBranch);

        $todayStats = $user->hasAppPermission('dashboard.today-stats')
            ? DashboardMetrics::summaryForToday($user, $selectedBranch->id)
            : null;
        $periodStats = $user->hasAppPermission('dashboard.period-stats')
            ? DashboardMetrics::summaryForRange($user, $selectedBranch->id, $startDate, $endDate)
            : null;
        $revenueChartDaily = $user->hasAppPermission('dashboard.revenue-chart')
            ? DashboardMetrics::dailyRevenueSeries($user, $selectedBranch->id, $startDate, $endDate)
            : null;
        $expensesChartDaily = $user->hasAppPermission('dashboard.expenses-chart')
            ? DashboardMetrics::dailyExpensesSeries($selectedBranch->id, $startDate, $endDate)
            : null;
        $orderTypeBreakdown = $user->hasAppPermission('dashboard.order-types')
            ? DashboardMetrics::orderTypeBreakdown($user, $selectedBranch->id, $startDate, $endDate)
            : null;
        $operationalComparison = $user->hasAppPermission('dashboard.operational-comparison')
            ? DashboardMetrics::operationalComparison($user, $selectedBranch->id, $startDate, $endDate)
            : null;
        $customerReceivables = $user->hasAppPermission('dashboard.receivables')
            ? AccountsOutstandingReport::receivables($user, $selectedBranch->id)
            : null;
        $supplierPayables = $user->hasAppPermission('dashboard.payables')
            ? AccountsOutstandingReport::payables($user, $selectedBranch->id)
            : null;
        $topFoodItems = $user->hasAppPermission('dashboard.top-items')
            ? DashboardMetrics::topFoodItems($user, $selectedBranch->id, $startDate, $endDate)
            : null;
        $lowStockItems = $user->hasAppPermission('dashboard.low-stock')
            ? DashboardMetrics::lowStockItems($selectedBranch->id)
            : null;
        $moneySourceBalances = $user->hasAppPermission('dashboard.funds-overview')
            ? $this->getMoneySourceBalances($selectedBranch->id, $user->company_id)
            : [];
        
        return view('dashboard', [
            'user' => $user,
            'availableBranches' => $availableBranches,
            'selectedBranch' => $selectedBranch,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'showShiftReminder' => $showShiftReminder,
            'shiftReminderBranchId' => $shiftReminderBranchId,
            'todayStats' => $todayStats,
            'periodStats' => $periodStats,
            'revenueChartDaily' => $revenueChartDaily,
            'expensesChartDaily' => $expensesChartDaily,
            'orderTypeBreakdown' => $orderTypeBreakdown,
            'operationalComparison' => $operationalComparison,
            'customerReceivables' => $customerReceivables,
            'supplierPayables' => $supplierPayables,
            'topFoodItems' => $topFoodItems,
            'lowStockItems' => $lowStockItems,
            'moneySourceBalances' => $moneySourceBalances,
        ]);
    }

    /**
     * @return array{0: bool, 1: ?int}
     */
    protected function resolveShiftReminder($user, Branch $selectedBranch): array
    {
        if ($user->isSuperAdmin()) {
            return [false, null];
        }

        $branchId = (int) $selectedBranch->id;

        if ($this->shiftService->hasActiveShift($branchId, (int) $user->id)) {
            session()->forget('shift_reminder');

            return [false, null];
        }

        return [true, $branchId];
    }

    /**
     * Get available branches for the user.
     */
    protected function getAvailableBranches($user)
    {
        if ($user->isSuperAdmin()) {
            return Branch::where('status', 'active')->orderBy('name')->get();
        } elseif ($user->isCompanyAdmin() && $user->company_id) {
            $branchId = current_branch_id();
            if ($branchId) {
                return Branch::where('id', $branchId)
                    ->where('status', 'active')
                    ->get();
            }

            return collect();
        } else {
            // Regular users - get their assigned branches
            $branches = $user->branches()
                ->where('status', 'active')
                ->orderBy('name')
                ->get();
            
            // Fallback to single branch_id
            if ($branches->isEmpty() && $user->branch_id) {
                $branch = Branch::where('id', $user->branch_id)
                    ->where('status', 'active')
                    ->first();
                if ($branch) {
                    return collect([$branch]);
                }
            }
            
            return $branches;
        }
    }

    /**
     * Get current balances for all money sources in a branch.
     */
    protected function getMoneySourceBalances(int $branchId, int $companyId): array
    {
        $moneySources = MoneySource::forPayments()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->whereHas('branches', function ($query) use ($branchId) {
                $query->where('branches.id', $branchId);
            })
            ->orderBy('type')
            ->orderBy('name')
            ->get();
        
        $balances = [];
        foreach ($moneySources as $moneySource) {
            $balances[] = [
                'money_source' => $moneySource,
                'balance' => $moneySource->getCurrentBalance($branchId),
            ];
        }
        
        return $balances;
    }

    /**
     * Super admin dashboard: companies overview, no branch/tenant data.
     */
    protected function superDashboard()
    {
        $companies = Company::withCount('branches')
            ->orderBy('name')
            ->get();

        $billing = \App\Support\PlatformBillingMetrics::summary();
        $recentInvoices = \App\Support\PlatformBillingMetrics::recentInvoices(6);
        $monthlySeries = \App\Support\PlatformBillingMetrics::monthlySeries(6);

        return view('dashboard-super', [
            'user' => Auth::user(),
            'companies' => $companies,
            'companiesCount' => $companies->count(),
            'billing' => $billing,
            'recentInvoices' => $recentInvoices,
            'monthlySeries' => $monthlySeries,
        ]);
    }
}
