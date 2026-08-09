<?php

use App\Models\Company;
use App\Models\MoneySource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildMoneySourcesTableForSqlite(
                ['CASH', 'BANK', 'APP', 'OWNER_DRAW'],
                includeSystemFields: true
            );
        } else {
            Schema::table('money_sources', function (Blueprint $table) {
                $table->boolean('is_system')->default(false)->after('active');
                $table->string('system_key', 64)->nullable()->after('is_system');
                $table->unique(['company_id', 'system_key']);
            });

            DB::statement("ALTER TABLE money_sources MODIFY COLUMN type ENUM('CASH', 'BANK', 'APP', 'OWNER_DRAW') NOT NULL DEFAULT 'CASH'");
        }

        Company::query()->each(function (Company $company) {
            MoneySource::withoutGlobalScopes()->firstOrCreate(
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
        });
    }

    public function down(): void
    {
        MoneySource::withoutGlobalScopes()
            ->where('system_key', MoneySource::SYSTEM_OWNER_WITHDRAWAL)
            ->forceDelete();

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildMoneySourcesTableForSqlite(
                ['CASH', 'BANK', 'APP'],
                includeSystemFields: false
            );
        } else {
            Schema::table('money_sources', function (Blueprint $table) {
                $table->dropUnique(['company_id', 'system_key']);
                $table->dropColumn(['is_system', 'system_key']);
            });

            DB::statement("ALTER TABLE money_sources MODIFY COLUMN type ENUM('CASH', 'BANK', 'APP') NOT NULL DEFAULT 'CASH'");
        }
    }

    /**
     * @param  list<string>  $types
     */
    protected function rebuildMoneySourcesTableForSqlite(array $types, bool $includeSystemFields): void
    {
        DB::statement('PRAGMA foreign_keys=OFF');

        Schema::create('money_sources_new', function (Blueprint $table) use ($types, $includeSystemFields) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('type', $types)->default('CASH');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->boolean('active')->default(true);

            if ($includeSystemFields) {
                $table->boolean('is_system')->default(false);
                $table->string('system_key', 64)->nullable();
            }

            $table->timestamps();
            $table->softDeletes();
            $table->index('company_id');
            $table->index('active');

            if ($includeSystemFields) {
                $table->unique(['company_id', 'system_key']);
            }
        });

        if ($includeSystemFields) {
            DB::statement('INSERT INTO money_sources_new (id, company_id, name, type, opening_balance, active, is_system, system_key, created_at, updated_at, deleted_at)
                SELECT id, company_id, name, type, opening_balance, active, 0, NULL, created_at, updated_at, deleted_at FROM money_sources');
        } else {
            DB::statement('INSERT INTO money_sources_new (id, company_id, name, type, opening_balance, active, created_at, updated_at, deleted_at)
                SELECT id, company_id, name, type, opening_balance, active, created_at, updated_at, deleted_at FROM money_sources');
        }

        Schema::drop('money_sources');
        Schema::rename('money_sources_new', 'money_sources');

        DB::statement('PRAGMA foreign_keys=ON');
    }
};
