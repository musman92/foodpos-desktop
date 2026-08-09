<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
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

        // Create default accounts for each company
        $companies = Company::all();
        
        foreach ($companies as $company) {
            foreach ($defaultAccounts as $accountData) {
                Account::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'name' => $accountData['name'],
                    ],
                    [
                        'type' => $accountData['type'],
                        'is_active' => true,
                        'is_deletable' => false, // Default accounts cannot be deleted
                    ]
                );
            }
        }
    }
}
