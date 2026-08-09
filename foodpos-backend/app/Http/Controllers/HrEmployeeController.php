<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeProfileRequest;
use App\Models\EmployeeLedgerEntry;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\HrAccess;
use App\Support\ListingPerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HrEmployeeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePermission('employees.index');
        $user = Auth::user();
        $perPage = ListingPerPage::fromRequest($request);

        $query = EmployeeProfile::query()
            ->with(['user.branches', 'user.branch'])
            ->where('company_id', $user->company_id)
            ->whereHas('user')
            ->when($request->filled('branch_id'), function ($q) use ($request, $user) {
                $branchId = (int) $request->branch_id;
                HrAccess::assertBranch($user, $branchId);
                $q->whereHas('user', function ($employeeQuery) use ($branchId) {
                    $employeeQuery->where('branch_id', $branchId)
                        ->orWhereHas('branches', fn ($branch) => $branch->where('branches.id', $branchId));
                });
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim((string) $request->search);
                $q->whereHas('user', function ($search) use ($term) {
                    $search->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('employment_status', $request->status))
            ->when(! $user->isCompanyAdmin() && ! $user->isSuperAdmin(), function ($q) use ($user) {
                $ids = BranchContext::allowedBranchIds($user);
                $q->whereHas('user', function ($employeeQuery) use ($ids) {
                    $employeeQuery->whereIn('branch_id', $ids)
                        ->orWhereHas('branches', fn ($branch) => $branch->whereIn('branches.id', $ids));
                });
            })
            ->orderByDesc('created_at');

        $employees = $query->paginate($perPage)->withQueryString();
        $branches = HrAccess::branchesFor($user);
        $employeeIds = $employees->getCollection()
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $ledgerBalances = [];
        if ($employeeIds !== []) {
            $credits = EmployeeLedgerEntry::query()
                ->selectRaw('employee_id, COALESCE(SUM(amount), 0) as total')
                ->whereIn('employee_id', $employeeIds)
                ->where('direction', 'credit')
                ->groupBy('employee_id')
                ->pluck('total', 'employee_id');
            $debits = EmployeeLedgerEntry::query()
                ->selectRaw('employee_id, COALESCE(SUM(amount), 0) as total')
                ->whereIn('employee_id', $employeeIds)
                ->where('direction', 'debit')
                ->groupBy('employee_id')
                ->pluck('total', 'employee_id');
            foreach ($employeeIds as $employeeId) {
                $ledgerBalances[$employeeId] = round(
                    (float) ($credits[$employeeId] ?? 0) - (float) ($debits[$employeeId] ?? 0),
                    2
                );
            }
        }

        return view('hr.employees.index', compact('employees', 'branches', 'perPage', 'ledgerBalances'));
    }

    public function create()
    {
        $this->authorizePermission('employees.store');

        return view('hr.employees.form', [
            'employeeProfile' => null,
            'branches' => HrAccess::branchesFor(Auth::user()),
        ]);
    }

    public function store(StoreEmployeeProfileRequest $request)
    {
        $data = $request->validated();
        $branchId = (int) $data['branch_id'];
        HrAccess::assertBranch(Auth::user(), $branchId);

        $profile = DB::transaction(function () use ($data, $branchId) {
            $staffUser = User::create([
                'company_id' => Auth::user()->company_id,
                'branch_id' => $branchId,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make(Str::random(32)),
                'type' => $data['operational_type'],
                'status' => 'active',
                'can_login' => false,
                'salary' => ($data['pay_frequency'] ?? null) === 'monthly' ? $data['pay_rate'] : 0,
            ]);
            $staffUser->branches()->sync([
                $branchId => ['is_primary' => true],
            ]);

            $openingBalance = round((float) ($data['opening_balance'] ?? 0), 2);

            unset(
                $data['name'],
                $data['email'],
                $data['phone'],
                $data['branch_id'],
                $data['operational_type'],
                $data['cnic_attachment'],
                $data['other_attachment'],
                $data['opening_balance']
            );
            $data['company_id'] = Auth::user()->company_id;
            $data['user_id'] = $staffUser->id;

            // Soft-deleted / orphaned profiles still occupy the unique user_id index.
            // When MySQL reuses a freed user id, a plain insert would collide.
            $profile = EmployeeProfile::withTrashed()->firstOrNew(['user_id' => $staffUser->id]);
            $profile->fill($data);
            if ($profile->trashed()) {
                $profile->restore();
            }
            $profile->save();

            if (! $profile->employee_number) {
                $profile->update(['employee_number' => sprintf('EMP-%05d', $profile->id)]);
            }

            if (abs($openingBalance) >= 0.01) {
                $entryDate = ! empty($data['hire_date'])
                    ? (string) $data['hire_date']
                    : local_today($branchId);

                EmployeeLedgerEntry::create([
                    'company_id' => Auth::user()->company_id,
                    'branch_id' => $branchId,
                    'employee_id' => $staffUser->id,
                    'entry_date' => $entryDate,
                    'type' => 'opening_balance',
                    'direction' => $openingBalance > 0 ? 'credit' : 'debit',
                    'amount' => abs($openingBalance),
                    'description' => 'Opening balance',
                    'created_by' => Auth::id(),
                ]);
            }

            return $profile->fresh();
        });
        $this->storeDocuments($request, $profile);

        return redirect()
            ->route('hr.employees.show', $profile)
            ->with('success', 'Employee created successfully. Grant login later from Users if needed.');
    }

    public function show(EmployeeProfile $employeeProfile)
    {
        $this->authorizeEmployee($employeeProfile, 'employees.index');
        $employeeProfile->load([
            'user.branches',
            'user.attendanceRecords' => fn ($q) => $q->latest('attendance_date')->limit(10),
            'user.employeeAdvances' => fn ($q) => $q->latest('advance_date')->limit(10),
            'user.employeePayments' => fn ($q) => $q->with('moneySource')->latest('payment_date')->limit(10),
        ]);
        $ledger = EmployeeLedgerEntry::query()
            ->where('employee_id', $employeeProfile->user_id)
            ->latest('entry_date')
            ->latest('id')
            ->paginate(20);
        $ledgerBalance = EmployeeLedgerEntry::balanceForEmployee((int) $employeeProfile->user_id);

        return view('hr.employees.show', compact('employeeProfile', 'ledger', 'ledgerBalance'));
    }

    public function edit(EmployeeProfile $employeeProfile)
    {
        $this->authorizeEmployee($employeeProfile, 'employees.update');

        return view('hr.employees.form', [
            'employeeProfile' => $employeeProfile->load('user'),
            'branches' => HrAccess::branchesFor(Auth::user()),
        ]);
    }

    public function update(StoreEmployeeProfileRequest $request, EmployeeProfile $employeeProfile)
    {
        $this->authorizeEmployee($employeeProfile, 'employees.update');
        $data = $request->validated();
        $branchId = (int) $data['branch_id'];
        HrAccess::assertBranch(Auth::user(), $branchId);

        DB::transaction(function () use ($employeeProfile, $data, $branchId) {
            $employeeProfile->user()->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'type' => $data['operational_type'],
                'branch_id' => $branchId,
                'salary' => $data['pay_frequency'] === 'monthly' ? $data['pay_rate'] : 0,
            ]);
            $employeeProfile->user->branches()->sync([
                $branchId => ['is_primary' => true],
            ]);

            unset(
                $data['name'],
                $data['email'],
                $data['phone'],
                $data['branch_id'],
                $data['operational_type'],
                $data['cnic_attachment'],
                $data['other_attachment']
            );
            $employeeProfile->update($data);
        });
        $this->storeDocuments($request, $employeeProfile);

        return redirect()
            ->route('hr.employees.show', $employeeProfile)
            ->with('success', 'Employee updated successfully.');
    }

    public function destroy(EmployeeProfile $employeeProfile)
    {
        $this->authorizeEmployee($employeeProfile, 'employees.destroy');
        Storage::disk('local')->deleteDirectory(
            "employee-documents/{$employeeProfile->company_id}/{$employeeProfile->id}"
        );

        DB::transaction(function () use ($employeeProfile) {
            $staffUser = $employeeProfile->user;
            // Free the unique user_id slot so a later auto-increment reuse cannot collide.
            $employeeProfile->forceDelete();

            // Soft-delete the login-disabled staff shell so it is not left orphaned.
            if ($staffUser && ! $staffUser->can_login) {
                $staffUser->delete();
            }
        });

        return redirect()
            ->route('hr.employees.index')
            ->with('success', 'Employee HR profile removed.');
    }

    protected function authorizePermission(string $permission): void
    {
        abort_unless(Auth::user()->hasAppPermission($permission), 403);
    }

    protected function authorizeEmployee(EmployeeProfile $profile, string $permission): void
    {
        $this->authorizePermission($permission);
        abort_unless((int) $profile->company_id === (int) Auth::user()->company_id, 403);

        if (! Auth::user()->isCompanyAdmin() && ! Auth::user()->isSuperAdmin()) {
            $branchIds = $profile->user->branches()->pluck('branches.id')->map(fn ($id) => (int) $id)->all();
            $branchIds[] = (int) $profile->user->branch_id;
            abort_unless(array_intersect($branchIds, BranchContext::allowedBranchIds(Auth::user())), 403);
        }
    }

    public function downloadDocument(EmployeeProfile $employeeProfile, string $document)
    {
        $this->authorizeEmployee($employeeProfile, 'employees.index');
        abort_unless(in_array($document, ['cnic', 'other'], true), 404);

        $pathField = $document === 'cnic' ? 'cnic_attachment_path' : 'other_attachment_path';
        $nameField = $document === 'cnic' ? 'cnic_attachment_name' : 'other_attachment_name';
        $path = $employeeProfile->{$pathField};

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download(
            $path,
            $employeeProfile->{$nameField} ?: basename($path)
        );
    }

    protected function storeDocuments(
        StoreEmployeeProfileRequest $request,
        EmployeeProfile $profile
    ): void {
        $updates = [];
        foreach (['cnic', 'other'] as $document) {
            $input = "{$document}_attachment";
            if (! $request->hasFile($input)) {
                continue;
            }

            $pathField = "{$document}_attachment_path";
            $nameField = "{$document}_attachment_name";
            $oldPath = $profile->{$pathField};
            $file = $request->file($input);
            $path = $file->store(
                "employee-documents/{$profile->company_id}/{$profile->id}",
                'local'
            );
            abort_unless($path, 500, 'Unable to store the employee document.');

            $updates[$pathField] = $path;
            $updates[$nameField] = $file->getClientOriginalName();
            if ($oldPath) {
                Storage::disk('local')->delete($oldPath);
            }
        }

        if ($updates !== []) {
            $profile->update($updates);
        }
    }
}
