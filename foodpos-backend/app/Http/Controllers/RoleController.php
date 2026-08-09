<?php

namespace App\Http\Controllers;

use App\Helpers\AppPermissions;
use App\Helpers\TenantDefaultRoles;
use App\Services\TenantRoleBootstrapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * List roles for the current tenant (company).
     */
    public function index()
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            abort(403, 'You do not have permission to view roles.');
        }

        $companyId = $user->company_id;
        $roles = Role::when($companyId !== null, function ($q) use ($companyId) {
            $q->where('company_id', $companyId)->orWhereNull('company_id');
        }, function ($q) {
            $q->whereNull('company_id');
        })->withCount('permissions')->orderBy('name')->get();

        $protectedRoleNames = TenantDefaultRoles::names();

        return view('roles.index', compact('roles', 'protectedRoleNames'));
    }

    /**
     * Show create role form.
     */
    public function create()
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            abort(403, 'You do not have permission to create roles.');
        }

        app(TenantRoleBootstrapService::class)->syncGlobalPermissions();
        $assignable = $this->assignablePermissionsForUser($user);
        $permissions = AppPermissions::groupedForFrontend($assignable);

        return view('roles.create', compact('permissions'));
    }

    /**
     * Store a new role for the current tenant.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            abort(403, 'You do not have permission to create roles.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::notIn(TenantDefaultRoles::names())],
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in($this->assignablePermissionsForUser($user))],
        ]);

        $guard = config('auth.defaults.guard');
        $companyId = $user->company_id; // null for super admin
        setPermissionsTeamId($companyId);

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => $guard,
        ]);
        if ($request->filled('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()->route('roles.index')->with('success', "Role '{$role->name}' created.");
    }

    /**
     * Show edit role form.
     */
    public function edit(Role $role)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            abort(403, 'You do not have permission to edit roles.');
        }
        if ($user->company_id !== null && $role->company_id !== null && $role->company_id !== $user->company_id) {
            abort(403, 'You cannot edit this role.');
        }

        app(TenantRoleBootstrapService::class)->syncGlobalPermissions();
        $assignable = $this->assignablePermissionsForUser($user);
        $permissions = AppPermissions::groupedForFrontend($assignable);
        $role->load('permissions');
        $isProtectedSystemRole = TenantDefaultRoles::isProtected($role->name);

        return view('roles.edit', compact('role', 'permissions', 'isProtectedSystemRole'));
    }

    /**
     * Update role.
     */
    public function update(Request $request, Role $role)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            abort(403, 'You do not have permission to update roles.');
        }
        if ($user->company_id !== null && $role->company_id !== null && $role->company_id !== $user->company_id) {
            abort(403, 'You cannot update this role.');
        }

        $nameRules = TenantDefaultRoles::isProtected($role->name)
            ? ['required', 'string', 'max:255', Rule::in([$role->name])]
            : ['required', 'string', 'max:255', Rule::notIn(TenantDefaultRoles::names())];

        $request->validate([
            'name' => $nameRules,
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in($this->assignablePermissionsForUser($user))],
        ]);

        $role->update(['name' => $request->name]);
        $role->syncPermissions($request->permissions ?? []);

        return redirect()->route('roles.index')->with('success', "Role '{$role->name}' updated.");
    }

    /**
     * Permissions that may be assigned to tenant roles (platform modules excluded for company admins).
     *
     * @return list<string>
     */
    protected function assignablePermissionsForUser($user): array
    {
        return $user->isSuperAdmin()
            ? AppPermissions::all()
            : AppPermissions::tenantScoped();
    }

    /**
     * Delete role.
     */
    public function destroy(Role $role)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            abort(403, 'You do not have permission to delete roles.');
        }
        if ($user->company_id !== null && $role->company_id !== null && $role->company_id !== $user->company_id) {
            abort(403, 'You cannot delete this role.');
        }

        if (TenantDefaultRoles::isProtected($role->name)) {
            return redirect()->route('roles.index')
                ->with('error', "The role \"{$role->name}\" is a system role and cannot be deleted.");
        }

        $name = $role->name;
        $role->delete();

        return redirect()->route('roles.index')->with('success', "Role '{$name}' deleted.");
    }
}
