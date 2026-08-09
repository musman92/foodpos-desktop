<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * MySQL: change primary key on a pivot table that has an FK on the old PK columns.
     * Drop FK -> drop PK -> add new PK -> re-add FK.
     */
    private function changePrimaryKeyWithFk(
        string $table,
        string $fkColumn,
        string $referencesTable,
        string $teamKey,
        string $pivotKey,
        string $modelKey,
        string $oldPrimaryName,
        string $newPrimaryName
    ): void {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            $fkName = $this->getForeignKeyName($table, $fkColumn);
            Schema::table($table, function (Blueprint $t) use ($fkName) {
                $t->dropForeign($fkName);
            });
        }

        Schema::table($table, function (Blueprint $t) use ($teamKey, $pivotKey, $modelKey, $newPrimaryName) {
            $t->dropPrimary([$pivotKey, $modelKey, 'model_type']);
            $t->primary([$teamKey, $pivotKey, $modelKey, 'model_type'], $newPrimaryName);
        });

        if ($driver === 'mysql') {
            Schema::table($table, function (Blueprint $t) use ($fkColumn, $referencesTable) {
                $t->foreign($fkColumn)->references('id')->on($referencesTable)->onDelete('cascade');
            });
        }
    }

    private function getForeignKeyName(string $table, string $column): string
    {
        $fks = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL",
            [DB::getDatabaseName(), $table, $column]
        );
        return $fks[0]->CONSTRAINT_NAME ?? $table . '_' . $column . '_foreign';
    }

    /**
     * Drop index on column only if it exists (MySQL index names can vary:
     * e.g. roles_team_foreign_key_index from create_permission_tables vs roles_company_id_index from add_company_id migration).
     */
    private function dropIndexIfExists(string $table, string $column): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->dropIndex([$column]);
            });
            return;
        }
        $rows = DB::select(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS 
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND SEQ_IN_INDEX = 1",
            [DB::getDatabaseName(), $table, $column]
        );
        if (count($rows) === 0) {
            return;
        }
        $indexName = $rows[0]->INDEX_NAME;
        if ($indexName === 'PRIMARY') {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($indexName) {
            $t->dropIndex($indexName);
        });
    }

    /**
     * Run the migrations.
     * Adds company_id (tenant) to Spatie permission tables for tenant-scoped roles.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teamKey = $columnNames['team_foreign_key'] ?? 'company_id';
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $modelKey = $columnNames['model_morph_key'] ?? 'model_id';

        if (empty($tableNames)) {
            return;
        }

        if (! Schema::hasColumn($tableNames['roles'], $teamKey)) {
            Schema::table($tableNames['roles'], function (Blueprint $table) use ($teamKey) {
                $table->unsignedBigInteger($teamKey)->nullable()->after('id');
                $table->index($teamKey);
                $table->dropUnique(['name', 'guard_name']);
                $table->unique([$teamKey, 'name', 'guard_name']);
            });
        }

        if (! Schema::hasColumn($tableNames['model_has_permissions'], $teamKey)) {
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($teamKey, $pivotPermission, $modelKey) {
                $table->unsignedBigInteger($teamKey)->nullable()->after($pivotPermission);
                $table->index($teamKey);
            });
            if (DB::getDriverName() !== 'sqlite') {
                $mhp = $tableNames['model_has_permissions'];
                $this->changePrimaryKeyWithFk($mhp, $pivotPermission, $tableNames['permissions'], $teamKey, $pivotPermission, $modelKey, 'model_has_permissions_permission_model_type_primary', 'model_has_permissions_team_primary');
            }
        }

        if (! Schema::hasColumn($tableNames['model_has_roles'], $teamKey)) {
            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamKey, $pivotRole, $modelKey) {
                $table->unsignedBigInteger($teamKey)->nullable()->after($pivotRole);
                $table->index($teamKey);
            });
            if (DB::getDriverName() !== 'sqlite') {
                $mhr = $tableNames['model_has_roles'];
                $this->changePrimaryKeyWithFk($mhr, $pivotRole, $tableNames['roles'], $teamKey, $pivotRole, $modelKey, 'model_has_roles_role_model_type_primary', 'model_has_roles_team_primary');
            }
        }

        app('cache')->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teamKey = $columnNames['team_foreign_key'] ?? 'company_id';
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $modelKey = $columnNames['model_morph_key'] ?? 'model_id';

        if (Schema::hasColumn($tableNames['roles'], $teamKey)) {
            Schema::table($tableNames['roles'], function (Blueprint $table) use ($teamKey) {
                $table->dropUnique([$teamKey, 'name', 'guard_name']);
                $table->unique(['name', 'guard_name']);
            });
            $this->dropIndexIfExists($tableNames['roles'], $teamKey);
            Schema::table($tableNames['roles'], function (Blueprint $table) use ($teamKey) {
                $table->dropColumn($teamKey);
            });
        }

        if (Schema::hasColumn($tableNames['model_has_permissions'], $teamKey)) {
            if (DB::getDriverName() === 'mysql') {
                $this->revertPrimaryKeyWithFk(
                    $tableNames['model_has_permissions'],
                    $pivotPermission,
                    $tableNames['permissions'],
                    $teamKey,
                    $pivotPermission,
                    $modelKey,
                    'model_has_permissions_team_primary',
                    'model_has_permissions_permission_model_type_primary'
                );
            } elseif (DB::getDriverName() !== 'sqlite') {
                Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($teamKey, $pivotPermission, $modelKey) {
                    $table->dropPrimary('model_has_permissions_team_primary');
                    $table->primary([$pivotPermission, $modelKey, 'model_type']);
                });
            }
            $this->dropIndexIfExists($tableNames['model_has_permissions'], $teamKey);
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($teamKey) {
                $table->dropColumn($teamKey);
            });
        }

        if (Schema::hasColumn($tableNames['model_has_roles'], $teamKey)) {
            if (DB::getDriverName() === 'mysql') {
                $this->revertPrimaryKeyWithFk(
                    $tableNames['model_has_roles'],
                    $pivotRole,
                    $tableNames['roles'],
                    $teamKey,
                    $pivotRole,
                    $modelKey,
                    'model_has_roles_team_primary',
                    'model_has_roles_role_model_type_primary'
                );
            } elseif (DB::getDriverName() !== 'sqlite') {
                Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamKey, $pivotRole, $modelKey) {
                    $table->dropPrimary('model_has_roles_team_primary');
                    $table->primary([$pivotRole, $modelKey, 'model_type']);
                });
            }
            $this->dropIndexIfExists($tableNames['model_has_roles'], $teamKey);
            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamKey) {
                $table->dropColumn($teamKey);
            });
        }
    }

    private function revertPrimaryKeyWithFk(
        string $table,
        string $fkColumn,
        string $referencesTable,
        string $teamKey,
        string $pivotKey,
        string $modelKey,
        string $currentPrimaryName,
        string $oldPrimaryName
    ): void {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            $fkName = $this->getForeignKeyName($table, $fkColumn);
            Schema::table($table, function (Blueprint $t) use ($fkName) {
                $t->dropForeign($fkName);
            });
        }
        Schema::table($table, function (Blueprint $t) use ($currentPrimaryName, $pivotKey, $modelKey, $oldPrimaryName) {
            $t->dropPrimary($currentPrimaryName);
            $t->primary([$pivotKey, $modelKey, 'model_type'], $oldPrimaryName);
        });
        if ($driver === 'mysql') {
            Schema::table($table, function (Blueprint $t) use ($fkColumn, $referencesTable) {
                $t->foreign($fkColumn)->references('id')->on($referencesTable)->onDelete('cascade');
            });
        }
    }
};
