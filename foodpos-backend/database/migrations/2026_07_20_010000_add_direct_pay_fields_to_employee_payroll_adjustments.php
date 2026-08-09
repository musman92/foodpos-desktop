<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_payroll_adjustments')) {
            return;
        }

        Schema::table('employee_payroll_adjustments', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_payroll_adjustments', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->default(0)->after('amount');
            }
            if (! Schema::hasColumn('employee_payroll_adjustments', 'employee_payment_id')) {
                $table->foreignId('employee_payment_id')
                    ->nullable()
                    ->after('payroll_item_id')
                    ->constrained('employee_payments')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee_payroll_adjustments')) {
            return;
        }

        Schema::table('employee_payroll_adjustments', function (Blueprint $table) {
            if (Schema::hasColumn('employee_payroll_adjustments', 'employee_payment_id')) {
                $table->dropConstrainedForeignId('employee_payment_id');
            }
            if (Schema::hasColumn('employee_payroll_adjustments', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }
        });
    }
};
