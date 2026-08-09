<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchDesktopKey;
use App\Models\Printer;
use App\Services\DesktopCommandService;
use App\Services\PrintJobService;
use App\Services\PrinterVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrinterSettingsController extends Controller
{
    public function __construct(
        private PrintJobService $printJobService,
        private DesktopCommandService $desktopCommandService,
        private PrinterVerificationService $printerVerification,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = Auth::user();
        $branches = $this->accessibleBranches($user);

        if ($branches->isEmpty()) {
            return redirect()->route('dashboard')
                ->with('error', 'No branches available for printer settings.');
        }

        $selectedBranchId = (int) $request->query('branch_id', $branches->first()->id);
        if (! $branches->contains('id', $selectedBranchId)) {
            $selectedBranchId = (int) $branches->first()->id;
        }

        $branch = $branches->firstWhere('id', $selectedBranchId);
        $printers = Printer::query()
            ->where('branch_id', $selectedBranchId)
            ->orderBy('role')
            ->orderByDesc('is_default')
            ->orderBy('title')
            ->get();

        $desktopKeys = BranchDesktopKey::query()
            ->where('branch_id', $selectedBranchId)
            ->orderByDesc('created_at')
            ->get();

        $recentPrintJobs = $this->printJobService->recentJobsForBranch($selectedBranchId, 5);

        return view('printer-settings.index', [
            'branches' => $branches,
            'branch' => $branch,
            'printers' => $printers,
            'desktopKeys' => $desktopKeys,
            'recentPrintJobs' => $recentPrintJobs,
            'newPlainKey' => session('new_desktop_key'),
        ]);
    }

    public function storePrinter(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $branches = $this->accessibleBranches($user);
        $branchIds = $branches->pluck('id')->all();

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', Rule::in($branchIds)],
            'title' => ['required', 'string', 'max:120'],
            'role' => ['required', 'in:kitchen,receipt'],
            'printing_mode' => ['required', 'in:browser,desktop'],
            'device_name' => ['nullable', 'string', 'max:255', 'required_if:printing_mode,desktop'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $branch = $branches->firstWhere('id', (int) $validated['branch_id']);

        if ($request->boolean('is_default')) {
            Printer::query()
                ->where('branch_id', $branch->id)
                ->where('role', $validated['role'])
                ->update(['is_default' => false]);
        }

        Printer::create([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            'title' => $validated['title'],
            'role' => $validated['role'],
            'printing_mode' => $validated['printing_mode'],
            'device_name' => $validated['device_name'] ?? null,
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('printer-settings.index', ['branch_id' => $branch->id])
            ->with('success', 'Printer added.');
    }

    public function updatePrinter(Request $request, Printer $printer): RedirectResponse
    {
        $this->authorizePrinter($printer);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'role' => ['required', 'in:kitchen,receipt'],
            'printing_mode' => ['required', 'in:browser,desktop'],
            'device_name' => ['nullable', 'string', 'max:255', 'required_if:printing_mode,desktop'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('is_default')) {
            Printer::query()
                ->where('branch_id', $printer->branch_id)
                ->where('role', $validated['role'])
                ->where('id', '!=', $printer->id)
                ->update(['is_default' => false]);
        }

        $printer->update([
            'title' => $validated['title'],
            'role' => $validated['role'],
            'printing_mode' => $validated['printing_mode'],
            'device_name' => $validated['device_name'] ?? null,
            'is_default' => $request->boolean('is_default'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('printer-settings.index', ['branch_id' => $printer->branch_id])
            ->with('success', 'Printer updated.');
    }

    public function destroyPrinter(Printer $printer): RedirectResponse
    {
        $this->authorizePrinter($printer);
        $branchId = $printer->branch_id;
        $printer->delete();

        return redirect()
            ->route('printer-settings.index', ['branch_id' => $branchId])
            ->with('success', 'Printer removed.');
    }

    public function generateDesktopKey(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $branches = $this->accessibleBranches($user);
        $branchIds = $branches->pluck('id')->all();

        $validated = $request->validate([
            'branch_id' => ['required', 'integer', Rule::in($branchIds)],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $branch = $branches->firstWhere('id', (int) $validated['branch_id']);
        $result = BranchDesktopKey::generateForBranch($branch, $validated['name']);

        return redirect()
            ->route('printer-settings.index', ['branch_id' => $branch->id])
            ->with('success', 'Branch key generated. Copy it and paste it into theFoodPOS Print APP.')
            ->with('new_desktop_key', $result['plain_key']);
    }

    public function revokeDesktopKey(BranchDesktopKey $branchDesktopKey): RedirectResponse
    {
        $this->authorizeDesktopKey($branchDesktopKey);
        $branchId = $branchDesktopKey->branch_id;
        $branchDesktopKey->update(['is_active' => false]);

        return redirect()
            ->route('printer-settings.index', ['branch_id' => $branchId])
            ->with('success', 'Desktop API key revoked.');
    }

    public function pingDesktopKey(BranchDesktopKey $branchDesktopKey): RedirectResponse
    {
        $this->authorizeDesktopKey($branchDesktopKey);

        if (! $branchDesktopKey->is_active) {
            return redirect()
                ->route('printer-settings.index', ['branch_id' => $branchDesktopKey->branch_id])
                ->with('error', 'This key is revoked.');
        }

        if (! $branchDesktopKey->isOnline()) {
            return redirect()
                ->route('printer-settings.index', ['branch_id' => $branchDesktopKey->branch_id])
                ->with('error', "\"{$branchDesktopKey->name}\" is not connected. Open the desktop app on that PC first.");
        }

        $this->desktopCommandService->queue($branchDesktopKey, 'ping');

        $code = $branchDesktopKey->connection_code ?? '------';

        return redirect()
            ->route('printer-settings.index', ['branch_id' => $branchDesktopKey->branch_id])
            ->with('success', "Connection test sent to \"{$branchDesktopKey->name}\" (code {$code}). The desktop app should flash a connected confirmation.");
    }

    public function fetchDesktopPrinters(BranchDesktopKey $branchDesktopKey): RedirectResponse
    {
        $this->authorizeDesktopKey($branchDesktopKey);

        if (! $branchDesktopKey->is_active) {
            return redirect()
                ->route('printer-settings.index', ['branch_id' => $branchDesktopKey->branch_id])
                ->with('error', 'This key is revoked.');
        }

        if (! $branchDesktopKey->isOnline()) {
            return redirect()
                ->route('printer-settings.index', ['branch_id' => $branchDesktopKey->branch_id])
                ->with('error', "\"{$branchDesktopKey->name}\" is not connected. Open the desktop app on that PC first.");
        }

        $this->desktopCommandService->queue($branchDesktopKey, 'fetch_printers');

        return redirect()
            ->route('printer-settings.index', [
                'branch_id' => $branchDesktopKey->branch_id,
                'fetch_key' => $branchDesktopKey->id,
            ])
            ->with('success', "Printer list requested from \"{$branchDesktopKey->name}\". Names will appear below in a few seconds — refresh if needed.");
    }

    public function desktopKeyStatus(BranchDesktopKey $branchDesktopKey): JsonResponse
    {
        $this->authorizeDesktopKey($branchDesktopKey);

        return response()->json([
            'id' => $branchDesktopKey->id,
            'is_online' => $branchDesktopKey->isOnline(),
            'connection_code' => $branchDesktopKey->connection_code,
            'last_heartbeat_at' => $branchDesktopKey->last_heartbeat_at?->toIso8601String(),
            'system_printers' => $branchDesktopKey->system_printers ?? [],
            'system_printers_at' => $branchDesktopKey->system_printers_at?->toIso8601String(),
        ]);
    }

    public function testPrint(Printer $printer): RedirectResponse
    {
        $this->authorizePrinter($printer);

        if ($printer->printing_mode === Printer::MODE_DIRECT) {
            $this->printJobService->queueTestPrint($printer);

            return redirect()
                ->route('printer-settings.index', ['branch_id' => $printer->branch_id])
                ->with('success', "Test print queued for \"{$printer->title}\". Check the desktop app on this branch.");
        }

        $job = $this->printJobService->queueTestPrint($printer);

        return redirect()->away($this->printJobService->printJobUrl($job, autoPrint: true));
    }

    public function verifyPrinter(Request $request, Printer $printer): JsonResponse
    {
        $this->authorizePrinter($printer);

        $deviceName = trim((string) $request->input('device_name', ''));
        if ($deviceName !== '') {
            $printer = clone $printer;
            $printer->device_name = $deviceName;
        }

        return response()->json($this->printerVerification->verify($printer));
    }

    /**
     * @return \Illuminate\Support\Collection<int, Branch>
     */
    private function accessibleBranches($user): \Illuminate\Support\Collection
    {
        if ($user->isSuperAdmin()) {
            return Branch::query()->where('status', 'active')->orderBy('name')->get();
        }

        if ($user->isCompanyAdmin() && $user->company_id) {
            return Branch::query()
                ->where('company_id', $user->company_id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get();
        }

        $branches = $user->branches()->where('status', 'active')->orderBy('name')->get();
        if ($branches->isEmpty() && $user->branch_id) {
            $branch = Branch::query()->where('id', $user->branch_id)->where('status', 'active')->first();
            if ($branch) {
                return collect([$branch]);
            }
        }

        return $branches;
    }

    private function authorizePrinter(Printer $printer): void
    {
        $user = Auth::user();
        if ($user->isSuperAdmin()) {
            return;
        }

        if ($user->company_id && (int) $printer->company_id === (int) $user->company_id) {
            return;
        }

        abort(403);
    }

    private function authorizeDesktopKey(BranchDesktopKey $key): void
    {
        $user = Auth::user();
        if ($user->isSuperAdmin()) {
            return;
        }

        if ($user->company_id && (int) $key->company_id === (int) $user->company_id) {
            return;
        }

        abort(403);
    }
}
