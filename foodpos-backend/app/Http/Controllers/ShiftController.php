<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\MoneySource;
use App\Models\Shift;
use App\Services\ShiftService;
use App\Support\BranchContext;
use App\Support\ListingPerPage;
use App\Support\ShiftZReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ShiftController extends Controller
{
    protected ShiftService $shiftService;

    public function __construct(ShiftService $shiftService)
    {
        $this->shiftService = $shiftService;
    }

    /**
     * Display a listing of shifts.
     */
    public function index(Request $request)
    {
        $perPage = ListingPerPage::fromRequest($request);
        $user = Auth::user();

        // Get shifts based on user access
        if ($user->isSuperAdmin()) {
            $shifts = Shift::with(['branch', 'openedBy', 'closedBy', 'moneySources'])
                ->latest('shift_date')
                ->latest('opened_at')
                ->paginate($perPage);
        } elseif ($user->isCompanyAdmin() && $user->company_id) {
            $shifts = Shift::where('company_id', $user->company_id)
                ->with(['branch', 'openedBy', 'closedBy', 'moneySources'])
                ->latest('shift_date')
                ->latest('opened_at')
                ->paginate($perPage);
        } else {
            // Regular users see shifts for their branches
            $branchIds = $user->branches()->pluck('branches.id')->toArray();
            if (empty($branchIds) && $user->branch_id) {
                $branchIds = [$user->branch_id];
            }
            
            $shifts = Shift::whereIn('branch_id', $branchIds)
                ->with(['branch', 'openedBy', 'closedBy', 'moneySources'])
                ->latest('shift_date')
                ->latest('opened_at')
                ->paginate($perPage);
        }

        return view('shifts.index', compact('shifts', 'perPage'));
    }

    /**
     * Show the form for starting a new shift.
     */
    public function create()
    {
        $user = Auth::user();

        // Get accessible branches
        if ($user->isSuperAdmin()) {
            $branches = Branch::where('status', 'active')->orderBy('name')->get();
        } elseif ($user->isCompanyAdmin() && $user->company_id) {
            $branches = Branch::where('company_id', $user->company_id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get();
        } else {
            $branchIds = $user->branches()->pluck('branches.id')->toArray();
            if (empty($branchIds) && $user->branch_id) {
                $branchIds = [$user->branch_id];
            }
            $branches = Branch::whereIn('id', $branchIds)
                ->where('status', 'active')
                ->orderBy('name')
                ->get();
        }

        // Get money sources for the selected branch
        $selectedBranchId = request()->get('branch_id', old('branch_id', $user->branch_id));
        $moneySources = collect();
        $hasBranchSelected = false;
        
        if ($selectedBranchId) {
            $branch = Branch::find($selectedBranchId);
            if ($branch) {
                $hasBranchSelected = true;
                // Get money sources associated with this branch
                $moneySources = $branch->moneySources()->forPayments()->where('active', true)->get();
                
                // If branch has no money sources, try to get all active money sources for the company
                if ($moneySources->isEmpty() && $user->company_id) {
                    $moneySources = MoneySource::forPayments()
                        ->where('company_id', $user->company_id)
                        ->where('active', true)
                        ->get();
                }
            }
        }

        return view('shifts.create', compact('branches', 'moneySources', 'selectedBranchId', 'hasBranchSelected'));
    }

    /**
     * Store a newly started shift.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'shift_date' => 'required|date',
            'money_sources' => 'required|array',
            'money_sources.*' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $shift = $this->shiftService->startShift(
                $validated['branch_id'],
                $user->id,
                $validated['shift_date'],
                $validated['money_sources'],
                $validated['notes'] ?? null
            );

            $request->session()->forget('shift_reminder');

            return redirect()
                ->route('shifts.index')
                ->with('success', 'Shift started successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified shift.
     */
    public function show(Shift $shift)
    {
        $user = Auth::user();

        // Check access
        if (!$user->isSuperAdmin() && $shift->company_id !== $user->company_id) {
            abort(403, 'You do not have access to this shift.');
        }

        $shift->load(['branch', 'openedBy', 'closedBy', 'moneySources']);

        // Calculate expected balances if shift is still active
        if ($shift->isActive()) {
            $expectedBalances = $this->shiftService->calculateExpectedBalances($shift);
        } else {
            $expectedBalances = [];
            foreach ($shift->moneySources as $moneySource) {
                $expectedBalances[$moneySource->id] = $moneySource->pivot->expected_balance ?? 0;
            }
        }

        return view('shifts.show', compact('shift', 'expectedBalances'));
    }

    /**
     * Show the form for closing a shift.
     */
    public function edit(Shift $shift)
    {
        $user = Auth::user();

        // Check access
        if (!$user->isSuperAdmin() && $shift->company_id !== $user->company_id) {
            abort(403, 'You do not have access to this shift.');
        }

        if ($shift->isClosed()) {
            return redirect()
                ->route('shifts.show', $shift)
                ->with('error', 'This shift is already closed.');
        }

        if (! $this->shiftService->userCanCloseShift($shift, $user->id, $user->isCompanyAdmin())) {
            abort(403, 'Only the cashier who opened this shift (or a company admin) can close it.');
        }

        $shift->load(['branch', 'moneySources']);

        // Calculate expected balances
        $expectedBalances = $this->shiftService->calculateExpectedBalances($shift);

        return view('shifts.close', compact('shift', 'expectedBalances'));
    }

    /**
     * Close the specified shift.
     */
    public function update(Request $request, Shift $shift)
    {
        $user = Auth::user();

        // Check access
        if (!$user->isSuperAdmin() && $shift->company_id !== $user->company_id) {
            abort(403, 'You do not have access to this shift.');
        }

        if ($shift->isClosed()) {
            return redirect()
                ->route('shifts.show', $shift)
                ->with('error', 'This shift is already closed.');
        }

        if (! $this->shiftService->userCanCloseShift($shift, $user->id, $user->isCompanyAdmin())) {
            abort(403, 'Only the cashier who opened this shift (or a company admin) can close it.');
        }

        $validated = $request->validate([
            'money_sources' => 'required|array',
            'money_sources.*' => 'required|numeric|min:0',
            'closing_date' => 'required|date',
            'closing_time' => 'required|date_format:H:i',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            // Combine date and time
            $closingDateTime = $validated['closing_date'] . ' ' . $validated['closing_time'];
            
            $shift = $this->shiftService->closeShift(
                $shift,
                $user->id,
                $validated['money_sources'],
                $closingDateTime,
                $validated['notes'] ?? null
            );

            return redirect()
                ->route('shifts.show', $shift)
                ->with('success', 'Shift closed successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * End-of-shift Z report (HTML).
     */
    public function zReport(Shift $shift)
    {
        $this->ensureShiftAccess($shift);

        $report = ShiftZReport::build($shift, $this->shiftService);

        return view('shifts.z-report', compact('report'));
    }

    /**
     * End-of-shift Z report (PDF download).
     */
    public function zReportPdf(Shift $shift): Response
    {
        $this->ensureShiftAccess($shift);

        $report = ShiftZReport::build($shift, $this->shiftService);
        $shiftDate = $shift->shift_date->format('Y-m-d');
        $branchSlug = Str::slug($shift->branch->name);
        $filename = sprintf('z-report-shift-%d-%s-%s.pdf', $shift->id, $shiftDate, $branchSlug);

        return Pdf::loadView('shifts.z-report-pdf', compact('report'))
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    /**
     * Get active shift for current user's branch.
     */
    public function getActiveShift(Request $request)
    {
        $user = Auth::user();
        $branchId = $request->get('branch_id', BranchContext::currentBranchId($user));

        if (! $branchId) {
            return response()->json(['has_active_shift' => false]);
        }

        $activeShift = $this->shiftService->getActiveShiftForUser((int) $branchId, (int) $user->id);

        return response()->json([
            'has_active_shift' => $activeShift !== null,
            'shift' => $activeShift ? [
                'id' => $activeShift->id,
                'branch_id' => $activeShift->branch_id,
                'branch_name' => $activeShift->branch->name,
                'opened_at' => $activeShift->opened_at->format('Y-m-d H:i:s'),
                'opened_by' => $activeShift->openedBy->name,
            ] : null,
        ]);
    }

    private function ensureShiftAccess(Shift $shift): void
    {
        $user = Auth::user();

        if (! $user->isSuperAdmin() && $shift->company_id !== $user->company_id) {
            abort(403, 'You do not have access to this shift.');
        }
    }
}

