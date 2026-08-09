<?php

use App\Models\Account;
use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FOC payment method on orders + default undeletable FOC expense account per tenant.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cash','card','digital_wallet','split','credit','foc') NULL");
        }

        Company::query()->orderBy('id')->each(function (Company $company) {
            $hasFocAccount = Account::withoutTenantScope()
                ->where('company_id', $company->id)
                ->where('name', 'FOC')
                ->exists();

            if ($hasFocAccount) {
                return;
            }

            Account::withoutTenantScope()->create([
                'company_id' => $company->id,
                'name' => 'FOC',
                'type' => 'expense',
                'is_active' => true,
                'is_deletable' => false,
            ]);
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("UPDATE orders SET payment_method = NULL WHERE payment_method = 'foc'");
            DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cash','card','digital_wallet','split','credit') NULL");
        }

        // Keep FOC accounts — removing them would drop history for live tenants.
    }
};
