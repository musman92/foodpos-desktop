<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use App\Support\ListingPerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BranchController extends Controller
{
    /**
     * Display a listing of branches.
     */
    public function index(Request $request)
    {
        $perPage = ListingPerPage::fromRequest($request);
        $user = Auth::user();

        // Super admins can see all branches
        if ($user->isSuperAdmin()) {
            $branches = Branch::with(['company'])
                ->withCount('users')
                ->latest()
                ->paginate($perPage);
        } 
        // Company admins can see branches in their company
        elseif ($user->isCompanyAdmin() && $user->company_id) {
            $branches = Branch::where('company_id', $user->company_id)
                ->with(['company'])
                ->withCount('users')
                ->latest()
                ->paginate($perPage);
        } 
        // Others can only see their own branch
        else {
            $branches = Branch::where('id', $user->branch_id)
                ->with(['company'])
                ->withCount('users')
                ->latest()
                ->paginate($perPage);
        }

        return view('branches.index', compact('branches'));
    }

    /**
     * Show the form for creating a new branch.
     */
    public function create()
    {
        $user = Auth::user();
        
        // Only super admins and company admins can create branches
        if (!$user->isSuperAdmin() && !$user->isCompanyAdmin()) {
            abort(403, 'You do not have permission to create branches.');
        }

        // Get companies for super admin, or use current company for company admin
        $companies = $user->isSuperAdmin() 
            ? \App\Models\Company::where('status', 'active')->orderBy('name')->get()
            : collect([$user->company]);

        // Get default timezone from company
        $defaultTimezone = $user->company->timezone ?? 'America/New_York';

        return view('branches.create', compact('companies', 'defaultTimezone'));
    }

    /**
     * Store a newly created branch.
     */
    public function store(StoreBranchRequest $request)
    {
        $user = Auth::user();

        // Only super admins and company admins can create branches
        if (!$user->isSuperAdmin() && !$user->isCompanyAdmin()) {
            abort(403, 'You do not have permission to create branches.');
        }

        // If company admin, ensure they can only create branches for their company
        if ($user->isCompanyAdmin()) {
            $request->merge(['company_id' => $user->company_id]);
        }

        $branch = Branch::create($request->validated());

        return redirect()->route('branches.index')
            ->with('success', "Branch '{$branch->name}' created successfully.");
    }

    /**
     * Display the specified branch.
     */
    public function show(Branch $branch)
    {
        $user = Auth::user();

        // Check access
        if (!$user->isSuperAdmin() && $branch->company_id !== $user->company_id) {
            abort(403, 'You do not have access to this branch.');
        }

        $branch->load(['company', 'users', 'tables', 'orders']);

        return view('branches.show', compact('branch'));
    }

    /**
     * Show the form for editing the specified branch.
     */
    public function edit(Branch $branch)
    {
        $user = Auth::user();

        // Check access
        if (!$user->isSuperAdmin() && $branch->company_id !== $user->company_id) {
            abort(403, 'You do not have access to this branch.');
        }

        // Only super admins and company admins can edit branches
        if (!$user->isSuperAdmin() && !$user->isCompanyAdmin()) {
            abort(403, 'You do not have permission to edit branches.');
        }

        // Get companies for super admin, or use current company for company admin
        $companies = $user->isSuperAdmin() 
            ? \App\Models\Company::where('status', 'active')->orderBy('name')->get()
            : collect([$user->company]);

        // Get default timezone from company
        $defaultTimezone = $branch->company->timezone ?? 'America/New_York';

        return view('branches.edit', compact('branch', 'companies', 'defaultTimezone'));
    }

    /**
     * Update the specified branch.
     */
    public function update(UpdateBranchRequest $request, Branch $branch)
    {
        $user = Auth::user();

        // Check access
        if (!$user->isSuperAdmin() && $branch->company_id !== $user->company_id) {
            abort(403, 'You do not have access to this branch.');
        }

        // Only super admins and company admins can update branches
        if (!$user->isSuperAdmin() && !$user->isCompanyAdmin()) {
            abort(403, 'You do not have permission to update branches.');
        }

        // If company admin, ensure they can only update branches for their company
        if ($user->isCompanyAdmin()) {
            $request->merge(['company_id' => $user->company_id]);
        }

        $branch->update($request->validated());

        if ($branch->company) {
            \App\Services\CompanyConfigService::warmSessionCaches($branch->company);
        }

        return redirect()->route('branches.index')
            ->with('success', "Branch '{$branch->name}' updated successfully.");
    }

    /**
     * Remove the specified branch.
     */
    public function destroy(Branch $branch)
    {
        $user = Auth::user();

        // Check access
        if (!$user->isSuperAdmin() && $branch->company_id !== $user->company_id) {
            abort(403, 'You do not have access to this branch.');
        }

        // Only super admins and company admins can delete branches
        if (!$user->isSuperAdmin() && !$user->isCompanyAdmin()) {
            abort(403, 'You do not have permission to delete branches.');
        }

        $branchName = $branch->name;
        $branch->delete();

        return redirect()->route('branches.index')
            ->with('success', "Branch '{$branchName}' deleted successfully.");
    }

    /**
     * Switch the current branch context.
     */
    public function switch(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
        ]);

        $user = Auth::user();
        $branchId = $request->input('branch_id');

        // Verify user has access to this branch
        if (!$user->canAccessMultipleBranches()) {
            return back()->withErrors(['error' => 'You do not have permission to switch branches.']);
        }

        // Verify branch belongs to user's company
        $branch = Branch::find($branchId);
        if ($branch->company_id !== $user->company_id) {
            return back()->withErrors(['error' => 'You do not have access to this branch.']);
        }

        // Update user's branch context (temporary, stored in session)
        \Illuminate\Support\Facades\Session::put('current_branch_id', $branchId);

        return back()->with('success', "Switched to {$branch->name}");
    }
}
