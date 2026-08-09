<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetCompanyTransactionsRequest;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Requests\UpdateCompanyPasswordRequest;
use App\Models\Company;
use App\Models\SecretLoginToken;
use App\Models\User;
use App\Services\CompanyAddonService;
use App\Services\CompanyConfigService;
use App\Services\CompanyReceiptLogoService;
use App\Services\CompanySetupService;
use App\Services\DemoResetService;
use App\Services\TenantTransactionalResetService;
use App\Support\CompanyAddons;
use App\Support\TenantTransactionalResetOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    /**
     * Display a listing of companies.
     * Only accessible to Super Admin.
     */
    public function index()
    {
        $user = Auth::user();

        // Only super admins can access this
        if (!$user->isSuperAdmin()) {
            abort(403, 'You do not have permission to view companies.');
        }

        $companies = Company::withCount(['branches', 'users'])
            ->latest()
            ->paginate(15);

        return view('companies.index', compact('companies'));
    }

    /**
     * Show the form for creating a new company.
     * Only accessible to Super Admin.
     */
    public function create()
    {
        $user = Auth::user();

        // Only super admins can access this
        if (!$user->isSuperAdmin()) {
            abort(403, 'You do not have permission to create companies.');
        }

        return view('companies.create', [
            'addonDefinitions' => CompanyAddons::definitions(),
            'billingIntervals' => \App\Support\TenantBilling::intervals(),
            'billingCurrencies' => config('platform_billing.currencies', ['USD']),
            'trialOptions' => config('platform_billing.trial_options', []),
            'defaultTrialDays' => (int) config('platform_billing.default_trial_days', 14),
        ]);
    }

    /**
     * Store a newly created company.
     * Only accessible to Super Admin.
     */
    public function store(StoreCompanyRequest $request, CompanySetupService $setupService)
    {
        $user = Auth::user();

        // Only super admins can access this
        if (!$user->isSuperAdmin()) {
            abort(403, 'You do not have permission to create companies.');
        }

        $data = $request->validated();

        // Generate slug from name if not provided
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
            
            // Ensure slug is unique
            $originalSlug = $data['slug'];
            $counter = 1;
            while (Company::where('slug', $data['slug'])->exists()) {
                $data['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        // Extract admin user data
        $adminData = [
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => $data['admin_password'],
        ];

        // Extract create_default_branch flag
        $createDefaultBranch = $request->has('create_default_branch') && $request->input('create_default_branch');

        // Remove admin fields from company data
        unset($data['admin_name'], $data['admin_email'], $data['admin_password'], $data['admin_password_confirmation'], $data['create_default_branch']);

        // Create company
        $settings = app(CompanyAddonService::class)->mergeAddonsIntoSettings(
            [],
            $request->input('addons', [])
        );
        $data['settings'] = $settings;
        $data['billing_enabled'] = $request->boolean('billing_enabled');

        if (! empty($data['billing_currency'])) {
            $data['billing_currency'] = strtoupper($data['billing_currency']);
        }

        $trialDays = (int) $request->input('trial_days', config('platform_billing.default_trial_days', 14));
        \App\Support\TenantBilling::applyTrialToCompanyData($data, $trialDays);

        $company = Company::create($data);

        // Setup company with admin user, default accounts, and optionally default branch
        $setupService->setupCompany($company, $adminData, $createDefaultBranch);

        return redirect()->route('companies.index')
            ->with('success', "Company '{$company->name}' created successfully with admin user and default setup.");
    }

    /**
     * Display the specified company.
     * Only accessible to Super Admin.
     */
    public function show(Company $company)
    {
        $user = Auth::user();

        // Only super admins can access this
        if (!$user->isSuperAdmin()) {
            abort(403, 'You do not have permission to view companies.');
        }

        $company->load(['branches', 'users', 'customers']);

        return view('companies.show', [
            'company' => $company,
            'outstandingBalance' => $company->outstandingBalance(),
            'transactionalResetOptions' => TenantTransactionalResetOptions::definitions(),
            'transactionalResetDependencies' => TenantTransactionalResetOptions::dependencies(),
            'transactionalResetRequiredBy' => TenantTransactionalResetOptions::requiredBy(),
        ]);
    }

    /**
     * Show the form for editing the specified company.
     * Only accessible to Super Admin.
     */
    public function edit(Company $company)
    {
        $user = Auth::user();

        // Only super admins can access this
        if (!$user->isSuperAdmin()) {
            abort(403, 'You do not have permission to edit companies.');
        }

        return view('companies.edit', [
            'company' => $company,
            'addonDefinitions' => CompanyAddons::definitions(),
            'companyAddons' => app(CompanyAddonService::class)->addonsForCompany($company),
            'billingIntervals' => \App\Support\TenantBilling::intervals(),
            'billingCurrencies' => config('platform_billing.currencies', ['USD']),
            'outstandingBalance' => $company->outstandingBalance(),
        ]);
    }

    /**
     * Update the specified company.
     * Only accessible to Super Admin.
     */
    public function update(UpdateCompanyRequest $request, Company $company)
    {
        $user = Auth::user();

        // Only super admins can access this
        if (!$user->isSuperAdmin()) {
            abort(403, 'You do not have permission to update companies.');
        }

        $data = $request->validated();
        $data['demo'] = $request->boolean('demo');
        $data['billing_enabled'] = $request->boolean('billing_enabled');

        if ($data['demo']) {
            $data['billing_enabled'] = false;
        }

        if (! empty($data['billing_currency'])) {
            $data['billing_currency'] = strtoupper($data['billing_currency']);
        }

        if ($request->filled('trial_ends_at')) {
            $data['trial_ends_at'] = $request->date('trial_ends_at');
        }

        if ($request->filled('billing_starts_at')) {
            $data['billing_starts_at'] = $request->date('billing_starts_at');
        }

        if ($request->filled('billing_due_date')) {
            $data['billing_due_date'] = $request->date('billing_due_date');
        }

        // Generate slug from name if not provided and name changed
        if (empty($data['slug']) && $data['name'] !== $company->name) {
            $data['slug'] = Str::slug($data['name']);
            
            // Ensure slug is unique (excluding current company)
            $originalSlug = $data['slug'];
            $counter = 1;
            while (Company::where('slug', $data['slug'])->where('id', '!=', $company->id)->exists()) {
                $data['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }
        } elseif (empty($data['slug'])) {
            // Keep existing slug if name hasn't changed
            unset($data['slug']);
        }

        $settings = $company->settings ?? [];
        $settings = app(CompanyAddonService::class)->mergeAddonsIntoSettings(
            $settings,
            $request->input('addons', [])
        );
        $data['settings'] = $settings;

        $company->update($data);
        CompanyConfigService::warmSessionCaches($company->fresh());

        return redirect()->route('companies.index')
            ->with('success', "Company '{$company->name}' updated successfully.");
    }

    /**
     * Generate a secret login token and show the one-time URL.
     * Only accessible to Super Admin.
     */
    public function secretLogin(Company $company)
    {
        $user = Auth::user();

        if (!$user->isSuperAdmin()) {
            abort(403, 'You do not have permission to generate secret login links.');
        }

        $adminUser = $company->users()->where('type', 'company_admin')->orderBy('id')->first()
            ?? $company->users()->orderBy('id')->first();

        if (!$adminUser) {
            return redirect()->route('companies.index')
                ->with('error', 'This company has no users. Add a user first.');
        }

        $tokenRecord = SecretLoginToken::generateForCompany($company, 15);
        $url = url('/secret-login/' . $tokenRecord->token);

        return view('companies.secret-login', [
            'company' => $company,
            'url' => $url,
            'expiresAt' => $tokenRecord->expires_at,
            'adminUser' => $adminUser,
        ]);
    }

    /**
     * Show the form to update password for a company user.
     * Only accessible to Super Admin.
     */
    public function editPassword(Company $company)
    {
        $user = Auth::user();

        if (!$user->isSuperAdmin()) {
            abort(403, 'You do not have permission to update company passwords.');
        }

        $company->load('users');
        $users = $company->users()->orderBy('name')->get();

        return view('companies.password', compact('company', 'users'));
    }

    /**
     * Update the password for a company user.
     * Only accessible to Super Admin.
     */
    public function updatePassword(UpdateCompanyPasswordRequest $request, Company $company)
    {
        $user = User::withoutGlobalScopes()->findOrFail($request->validated('user_id'));
        $user->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        return redirect()->route('companies.index')
            ->with('success', "Password updated successfully for {$user->name} ({$user->email}).");
    }

    /**
     * Generate a B&W receipt print logo from the tenant's current logo.
     * Only accessible to Super Admin.
     */
    public function generatePrintLogo(CompanyReceiptLogoService $logoService, Company $company)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin()) {
            abort(403, 'You do not have permission to update company logos.');
        }

        $result = $logoService->regenerateForCompany($company);

        return redirect()->route('companies.show', $company)
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function resetTransactionalData(
        ResetCompanyTransactionsRequest $request,
        TenantTransactionalResetService $resetService,
        Company $company
    ) {
        $user = Auth::user();
        if (! $user->isSuperAdmin()) {
            abort(403, 'You do not have permission to reset tenant transactional data.');
        }

        try {
            $summary = $resetService->reset($company, $request->normalizedOptions());

            $message = sprintf(
                'Transactional data reset for %s. Orders: %d, purchases: %d, customers: %d, supplier payments: %d.',
                $company->name,
                $summary['orders'],
                $summary['purchases'],
                $summary['customers'],
                $summary['supplier_payments'],
            );

            if (($summary['money_sources_reset'] ?? 0) > 0) {
                $message .= sprintf(' Money sources reset: %d.', $summary['money_sources_reset']);
            }

            return redirect()->route('companies.show', $company)
                ->with('success', $message);
        } catch (\Throwable $e) {
            return redirect()->route('companies.show', $company)
                ->with('error', 'Failed to reset transactional data: '.$e->getMessage());
        }
    }

    /**
     * Reset demo company data: wipe and seed Pizza Shop dataset (last 30 days).
     * Only accessible to Super Admin; company must be marked as demo.
     */
    public function resetDemoData(DemoResetService $demoResetService, Company $company)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            abort(403, 'You do not have permission to reset demo data.');
        }
        if (!$company->demo) {
            return redirect()->route('companies.show', $company)
                ->with('error', 'This company is not marked as a demo company.');
        }

        try {
            $demoResetService->resetDemoCompany($company);
            return redirect()->route('companies.show', $company)
                ->with('success', 'Demo data has been reset. A fresh 60-day dataset is now loaded.');
        } catch (\Throwable $e) {
            return redirect()->route('companies.show', $company)
                ->with('error', 'Failed to reset demo data: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified company.
     * Only accessible to Super Admin.
     */
    public function destroy(Company $company)
    {
        $user = Auth::user();

        // Only super admins can access this
        if (!$user->isSuperAdmin()) {
            abort(403, 'You do not have permission to delete companies.');
        }

        $companyName = $company->name;
        $company->delete();

        return redirect()->route('companies.index')
            ->with('success', "Company '{$companyName}' deleted successfully.");
    }
}

