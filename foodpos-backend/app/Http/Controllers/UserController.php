<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Models\Company;
use App\Models\Branch;
use App\Models\EmployeeProfile;
use App\Support\ListingPerPage;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $perPage = ListingPerPage::fromRequest($request);
        $user = Auth::user();

        // Super admins can see all users
        if ($user->isSuperAdmin()) {
            $users = User::with(['company', 'branches', 'roles'])
                ->latest()
                ->paginate($perPage);
        } 
        // Company admins can see users in their company
        elseif ($user->isCompanyAdmin() && $user->company_id) {
            $users = User::where('company_id', $user->company_id)
                ->with(['company', 'branches', 'roles'])
                ->latest()
                ->paginate($perPage);
        } 
        // Others can only see users in their branch
        else {
            $users = User::whereHas('branches', function($query) use ($user) {
                $query->where('branches.id', $user->branch_id);
            })
                ->with(['company', 'branches', 'roles'])
                ->latest()
                ->paginate($perPage);
        }

        return view('users.index', compact('users', 'perPage'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        $user = Auth::user();
        
        // Only super admins and company admins can create users
        if (!$user->isSuperAdmin() && !$user->isCompanyAdmin()) {
            abort(403, 'You do not have permission to create users.');
        }

        // Get companies for super admin, or use current company for company admin
        $companies = $user->isSuperAdmin() 
            ? Company::where('status', 'active')->orderBy('name')->get()
            : collect([$user->company]);

        // Get branches based on user type
        if ($user->isSuperAdmin()) {
            $branches = Branch::where('status', 'active')->with('company')->orderBy('name')->get();
        } elseif ($user->isCompanyAdmin()) {
            $branches = Branch::where('company_id', $user->company_id)
                ->where('status', 'active')
                ->with('company')
                ->orderBy('name')
                ->get();
        } else {
            $branches = collect([$user->branch]);
        }

        // Company-scoped roles for assignment (tenant + global)
        $companyId = $user->company_id;
        $roles = Role::when($companyId !== null, function ($q) use ($companyId) {
            $q->where('company_id', $companyId)->orWhereNull('company_id');
        }, function ($q) {
            $q->whereNull('company_id');
        })->orderBy('name')->get();

        $employees = EmployeeProfile::query()
            ->with('user')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereHas('user', fn ($q) => $q->where('can_login', false)->where('status', 'active'))
            ->orderByDesc('id')
            ->get();

        return view('users.create', compact('companies', 'branches', 'roles', 'employees'));
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request)
    {
        $user = Auth::user();

        // Check authorization
        if (!$user->isSuperAdmin() && !$user->isCompanyAdmin()) {
            abort(403, 'You do not have permission to create users.');
        }

        $validated = $request->validated();
        if (! empty($validated['employee_profile_id'])) {
            $validated['can_login'] = true;
        }

        $canLogin = (bool) ($validated['can_login'] ?? false);
        $this->applyPasswordForCanLogin($validated, $canLogin);

        // Set company_id if not super admin
        if (!$user->isSuperAdmin() && !isset($validated['company_id'])) {
            $validated['company_id'] = $user->company_id;
        }

        // Extract branches and primary branch before creating user
        $branches = $request->input('branches', []);
        $primaryBranchId = $request->input('primary_branch_id');
        $employeeProfileId = $validated['employee_profile_id'] ?? null;
        
        // Remove branches and primary_branch_id from validated data
        unset($validated['branches'], $validated['primary_branch_id'], $validated['employee_profile_id']);

        // Linking an existing employee enables login on that staff record instead of creating a duplicate.
        if ($employeeProfileId) {
            $profile = EmployeeProfile::query()
                ->with('user')
                ->whereKey($employeeProfileId)
                ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('company_id', $user->company_id))
                ->firstOrFail();
            abort_if($profile->user->can_login, 422, 'This employee already has login access.');

            $profile->user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? $profile->user->phone,
                'password' => $validated['password'] ?? $profile->user->password,
                'type' => $validated['type'],
                'status' => $validated['status'],
                'can_login' => true,
                'company_id' => $validated['company_id'] ?? $profile->user->company_id,
            ]);
            $newUser = $profile->user->fresh();
        } else {
            $newUser = User::create($validated);
        }

        // Sync branches if provided
        if (!empty($branches)) {
            $syncData = [];
            $hasPrimary = false;
            
            // First, set all branches with is_primary = false
            foreach ($branches as $branchId) {
                $syncData[$branchId] = [
                    'is_primary' => false
                ];
            }
            
            // Then, set the primary branch if specified
            if ($primaryBranchId && in_array($primaryBranchId, $branches)) {
                $syncData[$primaryBranchId]['is_primary'] = true;
                $hasPrimary = true;
            }
            
            // If no primary branch specified, make first one primary
            if (!$hasPrimary && !empty($branches)) {
                $firstBranchId = $branches[0];
                $syncData[$firstBranchId]['is_primary'] = true;
                $primaryBranchId = $firstBranchId;
            }
            
            $newUser->branches()->sync($syncData);
            
            // Set branch_id for backward compatibility (use primary branch)
            if ($primaryBranchId) {
                $newUser->update(['branch_id' => $primaryBranchId]);
            } elseif (!empty($branches)) {
                $newUser->update(['branch_id' => $branches[0]]);
            }
        }

        // Assign role if provided (use new user's company as team for Spatie)
        if ($request->filled('role')) {
            setPermissionsTeamId($newUser->company_id);
            $newUser->syncRoles([$request->role]);
        }

        $message = $employeeProfileId
            ? "Login enabled for employee '{$newUser->name}'."
            : "User '{$newUser->name}' created successfully.";

        return redirect()->route('users.index')
            ->with('success', $message);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $authUser = Auth::user();

        // Check authorization
        if ($authUser->isSuperAdmin()) {
            // Super admin can see all users
        } elseif ($authUser->isCompanyAdmin() && $authUser->company_id !== $user->company_id) {
            abort(403, 'You do not have permission to view this user.');
        } elseif (!$authUser->isSuperAdmin() && !$authUser->isCompanyAdmin() && $authUser->branch_id !== $user->branch_id) {
            abort(403, 'You do not have permission to view this user.');
        }

        $user->load(['company', 'branches', 'roles']);

        return view('users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user)
    {
        $authUser = Auth::user();

        // Check authorization
        if ($authUser->isSuperAdmin()) {
            // Super admin can edit all users
        } elseif ($authUser->isCompanyAdmin() && $authUser->company_id !== $user->company_id) {
            abort(403, 'You do not have permission to edit this user.');
        } elseif (!$authUser->isSuperAdmin() && !$authUser->isCompanyAdmin()) {
            abort(403, 'You do not have permission to edit users.');
        }

        // Get companies for super admin, or use current company for company admin
        $companies = $authUser->isSuperAdmin() 
            ? Company::where('status', 'active')->orderBy('name')->get()
            : collect([$authUser->company]);

        // Get branches based on user type
        if ($authUser->isSuperAdmin()) {
            $branches = Branch::where('status', 'active')->with('company')->orderBy('name')->get();
        } elseif ($authUser->isCompanyAdmin()) {
            $branches = Branch::where('company_id', $authUser->company_id)
                ->where('status', 'active')
                ->with('company')
                ->orderBy('name')
                ->get();
        } else {
            $branches = collect([$authUser->branch]);
        }

        $user->load(['roles', 'branches']);

        // Company-scoped roles for assignment (tenant + global)
        $companyId = $authUser->company_id;
        $roles = Role::when($companyId !== null, function ($q) use ($companyId) {
            $q->where('company_id', $companyId)->orWhereNull('company_id');
        }, function ($q) {
            $q->whereNull('company_id');
        })->orderBy('name')->get();
        
        // Get selected branch IDs for the user
        $selectedBranches = $user->branches->pluck('id')->toArray();
        $primaryBranch = $user->primaryBranch();
        $primaryBranchId = $primaryBranch ? $primaryBranch->id : null;

        return view('users.edit', compact('user', 'companies', 'branches', 'roles', 'selectedBranches', 'primaryBranchId'));
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $authUser = Auth::user();

        // Check authorization
        if ($authUser->isSuperAdmin()) {
            // Super admin can update all users
        } elseif ($authUser->isCompanyAdmin() && $authUser->company_id !== $user->company_id) {
            abort(403, 'You do not have permission to update this user.');
        } elseif (!$authUser->isSuperAdmin() && !$authUser->isCompanyAdmin()) {
            abort(403, 'You do not have permission to update users.');
        }

        $validated = $request->validated();

        $canLogin = (bool) ($validated['can_login'] ?? false);

        if ($user->id === $authUser->id && ! $canLogin) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['can_login' => 'You cannot disable sign-in for your own account.']);
        }

        $this->applyPasswordForCanLogin($validated, $canLogin, isUpdate: true);

        // Set company_id if not super admin
        if (!$authUser->isSuperAdmin() && !isset($validated['company_id'])) {
            $validated['company_id'] = $authUser->company_id;
        }

        // Extract branches and primary branch before updating user
        $branches = $request->input('branches', []);
        $primaryBranchId = $request->input('primary_branch_id');
        
        // Remove branches and primary_branch_id from validated data
        unset($validated['branches'], $validated['primary_branch_id']);

        // Update user
        $user->update($validated);

        // Sync branches if provided
        if ($request->has('branches')) {
            if (!empty($branches)) {
                $syncData = [];
                $hasPrimary = false;
                
                // First, set all branches with is_primary = false
                foreach ($branches as $branchId) {
                    $syncData[$branchId] = [
                        'is_primary' => false
                    ];
                }
                
                // Then, set the primary branch if specified
                if ($primaryBranchId && in_array($primaryBranchId, $branches)) {
                    $syncData[$primaryBranchId]['is_primary'] = true;
                    $hasPrimary = true;
                }
                
                // If no primary branch specified but branches exist, keep existing primary or set first as primary
                if (!$hasPrimary && !empty($branches)) {
                    $existingPrimary = $user->primaryBranch();
                    if ($existingPrimary && in_array($existingPrimary->id, $branches)) {
                        $syncData[$existingPrimary->id]['is_primary'] = true;
                        $primaryBranchId = $existingPrimary->id;
                    } else {
                        $firstBranchId = $branches[0];
                        $syncData[$firstBranchId]['is_primary'] = true;
                        $primaryBranchId = $firstBranchId;
                    }
                }
                
                $user->branches()->sync($syncData);
                
                // Update branch_id for backward compatibility (use primary branch)
                if ($primaryBranchId) {
                    $user->update(['branch_id' => $primaryBranchId]);
                } elseif (!empty($branches)) {
                    $user->update(['branch_id' => $branches[0]]);
                }
            } else {
                // If branches array is empty, remove all branch associations
                $user->branches()->detach();
                $user->update(['branch_id' => null]);
            }
        }

        // Update role (use user's company as team for Spatie)
        setPermissionsTeamId($user->company_id);
        if ($request->filled('role')) {
            $user->syncRoles([$request->role]);
        } else {
            $user->syncRoles([]);
        }

        return redirect()->route('users.index')
            ->with('success', "User '{$user->name}' updated successfully.");
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user)
    {
        $authUser = Auth::user();

        // Prevent self-deletion
        if ($authUser->id === $user->id) {
            return redirect()->route('users.index')
                ->withErrors(['error' => 'You cannot delete your own account.']);
        }

        // Check authorization
        if ($authUser->isSuperAdmin()) {
            // Super admin can delete all users
        } elseif ($authUser->isCompanyAdmin() && $authUser->company_id !== $user->company_id) {
            abort(403, 'You do not have permission to delete this user.');
        } elseif (!$authUser->isSuperAdmin() && !$authUser->isCompanyAdmin()) {
            abort(403, 'You do not have permission to delete users.');
        }

        $userName = $user->name;
        $user->delete();

        return redirect()->route('users.index')
            ->with('success', "User '{$userName}' deleted successfully.");
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function applyPasswordForCanLogin(array &$validated, bool $canLogin, bool $isUpdate = false): void
    {
        unset($validated['password_confirmation']);

        if ($canLogin) {
            if ($isUpdate && empty($validated['password'])) {
                unset($validated['password']);
            }
        } else {
            $validated['password'] = Str::password(40);
        }
    }
}
