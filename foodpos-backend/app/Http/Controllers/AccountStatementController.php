<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\EmployeeLedgerEntry;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CompanyReceiptBrandingService;
use App\Support\AccountStatementService;
use App\Support\HrAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AccountStatementController extends Controller
{
    public function __construct(
        protected AccountStatementService $accountStatementService
    ) {}

    public function index(Request $request): RedirectResponse
    {
        $this->authorizeModule();

        return redirect()->route('reports.index', array_merge(
            $request->only(['type', 'party_id', 'from', 'to', 'branch_id']),
            ['report' => 'account-statement']
        ));
    }

    public function pdf(Request $request): Response|RedirectResponse
    {
        $this->authorizeModule();

        $ctx = $this->resolveStatement($request);

        if (! $ctx['statement'] || ! $ctx['party']) {
            return redirect()
                ->route('reports.index', array_merge(
                    $request->only(['type', 'party_id', 'from', 'to', 'branch_id']),
                    ['report' => 'account-statement']
                ))
                ->with('error', 'Select a customer, supplier, or employee and view the statement before exporting.');
        }

        $slug = Str::slug($ctx['party']->name) ?: 'party';
        $filename = sprintf(
            'account-statement-%s-%s-%s.pdf',
            $ctx['type'],
            $slug,
            now()->format('Y-m-d')
        );

        return Pdf::loadView('account-statements.pdf', $ctx)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    public function search(Request $request): JsonResponse
    {
        $this->authorizeModule();

        $user = Auth::user();
        $validated = $request->validate([
            'type' => ['required', 'in:customer,supplier,employee'],
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $q = trim($validated['q']);
        $type = $validated['type'];

        if ($type === 'customer') {
            $digits = preg_replace('/\D/', '', $q);
            $rows = Customer::query()
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->where(function ($query) use ($q, $digits) {
                    $query->where('name', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%');
                    if ($digits !== '') {
                        $query->orWhere('phone', 'like', '%'.$q.'%')
                            ->orWhereRaw(
                                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') LIKE ?",
                                ['%'.$digits.'%']
                            );
                    }
                })
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'phone', 'balance']);

            return response()->json($rows->map(fn (Customer $c) => [
                'id' => $c->id,
                'label' => $c->name
                    .($c->phone ? ' · '.$c->phone : '')
                    .((float) $c->balance > 0 ? ' — owes '.format_currency($c->balance) : ''),
                'name' => $c->name,
            ]));
        }

        if ($type === 'supplier') {
            $rows = Supplier::query()
                ->where('company_id', $user->company_id)
                ->where('status', 'active')
                ->where(function ($query) use ($q) {
                    $query->where('name', 'like', '%'.$q.'%')
                        ->orWhere('phone', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%');
                })
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'phone', 'balance']);

            return response()->json($rows->map(fn (Supplier $s) => [
                'id' => $s->id,
                'label' => $s->name
                    .($s->phone ? ' · '.$s->phone : '')
                    .((float) $s->balance > 0 ? ' — owed '.format_currency($s->balance) : ''),
                'name' => $s->name,
            ]));
        }

        $digits = preg_replace('/\D/', '', $q);
        $rows = HrAccess::employeeUsers($user)
            ->with('employeeProfile:id,user_id,designation')
            ->where(function ($query) use ($q, $digits) {
                $query->where('name', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%');
                if ($digits !== '') {
                    $query->orWhere('phone', 'like', '%'.$q.'%');
                }
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone']);

        return response()->json($rows->map(function (User $employee) {
            $balance = EmployeeLedgerEntry::balanceForEmployee((int) $employee->id);
            $designation = $employee->employeeProfile?->designation;
            $balanceLabel = '';
            if (abs($balance) >= 0.01) {
                $balanceLabel = $balance > 0
                    ? ' — payable '.format_currency($balance)
                    : ' — advance '.format_currency(abs($balance));
            }

            return [
                'id' => $employee->id,
                'label' => $employee->name
                    .($designation ? ' · '.$designation : '')
                    .($employee->phone ? ' · '.$employee->phone : '')
                    .$balanceLabel,
                'name' => $employee->name,
            ];
        }));
    }

    /**
     * @return array{
     *     type: string,
     *     typeLabel: string,
     *     partyId: ?int,
     *     partyLabel: string,
     *     from: ?string,
     *     to: ?string,
     *     branch: ?Branch,
     *     statement: ?array,
     *     party: Customer|Supplier|User|null,
     *     partyBalance: float,
     *     partyBalanceHint: string,
     *     branchError: ?string,
     *     companyName: string
     * }
     */
    public function resolveStatement(Request $request): array
    {
        $user = Auth::user();
        $branchIdRaw = $request->has('branch_id') ? $request->input('branch_id') : $user->branch_id;
        $branchId = ($branchIdRaw !== null && $branchIdRaw !== '') ? (int) $branchIdRaw : null;
        $branch = $branchId ? Branch::find($branchId) : null;

        $type = $request->input('type', 'customer');
        if (! in_array($type, ['customer', 'supplier', 'employee'], true)) {
            $type = 'customer';
        }

        $partyId = $request->filled('party_id') ? (int) $request->input('party_id') : null;
        $from = $request->filled('from') ? $request->input('from') : null;
        $to = $request->filled('to') ? $request->input('to') : null;

        $statement = null;
        $party = null;
        $partyLabel = '';
        $partyBalance = 0.0;
        $partyBalanceHint = '';

        if ($partyId && $branchId) {
            if ($type === 'customer') {
                $party = Customer::withoutTenantScope()
                    ->where('company_id', $user->company_id)
                    ->where('id', $partyId)
                    ->firstOrFail();
                $partyLabel = $party->name;
                $partyBalance = (float) $party->balance;
                $partyBalanceHint = 'Amount customer owes (company-wide)';
                $statement = $this->accountStatementService->customerStatement(
                    $party,
                    (int) $user->company_id,
                    $branchId,
                    $from,
                    $to
                );
            } elseif ($type === 'supplier') {
                $party = Supplier::withoutTenantScope()
                    ->where('company_id', $user->company_id)
                    ->where('id', $partyId)
                    ->firstOrFail();
                $partyLabel = $party->name;
                $partyBalance = (float) $party->balance;
                $partyBalanceHint = 'Amount you owe supplier (company-wide)';
                $statement = $this->accountStatementService->supplierStatement(
                    $party,
                    (int) $user->company_id,
                    $branchId,
                    $from,
                    $to
                );
            } else {
                $party = HrAccess::employeeUsers($user)
                    ->whereKey($partyId)
                    ->firstOrFail();
                $partyLabel = $party->name;
                $partyBalance = EmployeeLedgerEntry::balanceForEmployee((int) $party->id);
                $partyBalanceHint = abs($partyBalance) < 0.009
                    ? 'Settled (company-wide)'
                    : ($partyBalance > 0
                        ? 'Amount payable to employee (company-wide)'
                        : 'Employee advance / overpay (company-wide)');
                $statement = $this->accountStatementService->employeeStatement(
                    $party,
                    (int) $user->company_id,
                    $branchId,
                    $from,
                    $to
                );
            }
        }

        $branding = CompanyReceiptBrandingService::get($user->company);
        $companyBranding = $branding['company'] ?? [];
        $branchBranding = $branchId && isset($branding['branches'][$branchId])
            ? $branding['branches'][$branchId]
            : null;

        $typeLabel = match ($type) {
            'supplier' => 'Supplier',
            'employee' => 'Employee',
            default => 'Customer',
        };

        return [
            'type' => $type,
            'typeLabel' => $typeLabel,
            'partyId' => $partyId,
            'partyLabel' => $partyLabel,
            'from' => $from,
            'to' => $to,
            'branch' => $branch,
            'statement' => $statement,
            'party' => $party,
            'partyBalance' => $partyBalance,
            'partyBalanceHint' => $partyBalanceHint,
            'branchError' => $branchId ? null : 'Select a branch from the top bar to view account statements for that branch.',
            'companyName' => $companyBranding['name'] ?? $user->company?->name ?? config('app.name'),
            'businessName' => $companyBranding['name'] ?? $user->company?->name ?? config('app.name'),
            'businessAddress' => $companyBranding['address'] ?? $branchBranding['address'] ?? $user->company?->address,
            'businessPhone' => $companyBranding['phone'] ?? $branchBranding['phone'] ?? $user->company?->phone,
            'generatedAt' => now(),
        ];
    }

    private function authorizeModule(): void
    {
        abort_unless(Auth::user()->hasAppPermission('account-statements.index'), 403);
    }
}
