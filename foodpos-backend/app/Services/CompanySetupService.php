<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\MoneySource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CompanySetupService
{
    public function __construct(
        protected TenantRoleBootstrapService $tenantRoleBootstrap,
    ) {}

    /**
     * Setup a newly created company with default data.
     *
     * @param  array  $adminData  Admin user data (name, email, password)
     * @param  bool  $createDefaultBranch  Whether to create a default branch
     * @return array Created resources
     */
    public function setupCompany(Company $company, array $adminData, bool $createDefaultBranch = true): array
    {
        $created = [];

        DB::transaction(function () use ($company, $adminData, $createDefaultBranch, &$created) {
            // Create company admin user
            $admin = $this->createAdminUser($company, $adminData);
            $created['admin'] = $admin;

            // Create default accounts
            $accounts = $this->createDefaultAccounts($company);
            $created['accounts'] = $accounts;

            $branch = null;

            // Create default branch if requested
            if ($createDefaultBranch) {
                $branch = $this->createDefaultBranch($company);
                $created['branch'] = $branch;

                // Associate admin with the default branch
                $admin->update(['branch_id' => $branch->id]);
                // Attach branch to user (many-to-many relationship)
                $admin->branches()->attach($branch->id, ['is_primary' => true]);
            }

            $created['money_source'] = $this->createDefaultCashMoneySource($company, $branch);
            $created['owner_withdrawal_source'] = $this->createOwnerWithdrawalMoneySource($company);

            $this->tenantRoleBootstrap->bootstrapNewCompany($company, $admin);
        });

        return $created;
    }

    /**
     * Create the company admin user.
     */
    protected function createAdminUser(Company $company, array $adminData): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => $adminData['name'],
            'email' => $adminData['email'],
            'password' => Hash::make($adminData['password']),
            'type' => 'company_admin',
            'status' => 'active',
            'can_login' => true,
        ]);
    }

    /**
     * Create default accounts for the company.
     */
    protected function createDefaultAccounts(Company $company): array
    {
        $defaultAccounts = [
            ['name' => 'Sales', 'type' => 'income'],
            ['name' => 'Refund', 'type' => 'expense'],
            ['name' => 'Purchase', 'type' => 'expense'],
            ['name' => 'Salary', 'type' => 'expense'],
            ['name' => 'Maintenance', 'type' => 'expense'],
            ['name' => 'Utility Bills', 'type' => 'expense'],
            ['name' => 'Guest', 'type' => 'expense'],
            ['name' => 'FOC', 'type' => 'expense'],
        ];

        $accounts = [];
        foreach ($defaultAccounts as $accountData) {
            $accounts[] = Account::withoutTenantScope()->create([
                'company_id' => $company->id,
                'name' => $accountData['name'],
                'type' => $accountData['type'],
                'is_active' => true,
                'is_deletable' => false, // Default accounts cannot be deleted
            ]);
        }

        return $accounts;
    }

    /**
     * Create the default Cash money source for POS and shifts.
     */
    protected function createDefaultCashMoneySource(Company $company, ?Branch $branch = null): MoneySource
    {
        $cashSource = MoneySource::withoutTenantScope()->create([
            'company_id' => $company->id,
            'name' => 'Cash',
            'type' => 'CASH',
            'opening_balance' => 0,
            'active' => true,
        ]);

        if ($branch) {
            $cashSource->branches()->attach($branch->id);
        }

        return $cashSource;
    }

    protected function createOwnerWithdrawalMoneySource(Company $company): MoneySource
    {
        return MoneySource::withoutTenantScope()->firstOrCreate(
            [
                'company_id' => $company->id,
                'system_key' => MoneySource::SYSTEM_OWNER_WITHDRAWAL,
            ],
            [
                'name' => 'Owner Withdrawal',
                'type' => 'OWNER_DRAW',
                'opening_balance' => 0,
                'active' => true,
                'is_system' => true,
            ]
        );
    }

    /**
     * Create a default branch for the company.
     */
    protected function createDefaultBranch(Company $company): Branch
    {
        // Generate branch name from company name
        $branchName = $company->name.' - Main Branch';

        // Generate branch code from company name (take first 3 letters, pad if needed)
        $companyInitials = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $company->name), 0, 3));
        if (strlen($companyInitials) < 3) {
            $companyInitials = str_pad($companyInitials, 3, 'X');
        }
        $branchCode = $companyInitials.'001';

        // Ensure branch code is unique
        $originalCode = $branchCode;
        $counter = 1;
        while (Branch::withoutTenantScope()
            ->where('company_id', $company->id)
            ->where('code', $branchCode)
            ->exists()) {
            $branchCode = $originalCode.'-'.$counter;
            $counter++;
        }

        return Branch::withoutTenantScope()->create([
            'company_id' => $company->id,
            'name' => $branchName,
            'code' => $branchCode,
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->address,
            'timezone' => $company->timezone ?? 'America/New_York',
            'status' => 'active',
        ]);
    }
}
