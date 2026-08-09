<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_profiles')) {
            Schema::create('employee_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('employee_number')->nullable();
                $table->string('designation')->nullable();
                $table->string('department')->nullable();
                $table->date('hire_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('employment_status', 20)->default('active');
                $table->string('pay_frequency', 20)->default('monthly');
                $table->decimal('pay_rate', 12, 2)->default(0);
                $table->decimal('standard_hours_per_day', 5, 2)->default(8);
                $table->decimal('overtime_rate', 12, 2)->default(0);
                $table->string('short_hours_policy', 20)->default('full_day');
                $table->json('working_days')->nullable();
                $table->string('national_id')->nullable();
                $table->text('address')->nullable();
                $table->string('emergency_contact_name')->nullable();
                $table->string('emergency_contact_phone')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('bank_account')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'employee_number']);
                $table->index(['company_id', 'employment_status']);
                $table->index(['company_id', 'pay_frequency']);
            });
        }

        if (! Schema::hasTable('attendance_records')) {
            Schema::create('attendance_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('leave_request_id')->nullable();
                $table->date('attendance_date');
                $table->dateTime('clock_in')->nullable();
                $table->dateTime('clock_out')->nullable();
                $table->unsignedInteger('break_minutes')->default(0);
                $table->unsignedInteger('worked_minutes')->default(0);
                $table->unsignedInteger('regular_minutes')->default(0);
                $table->unsignedInteger('overtime_minutes')->default(0);
                $table->string('status', 20)->default('present');
                $table->string('source', 20)->default('manual');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'employee_id', 'attendance_date']);
                $table->index(['company_id', 'branch_id', 'attendance_date']);
            });
        }

        if (! Schema::hasTable('employee_leave_requests')) {
            Schema::create('employee_leave_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
                $table->string('leave_type', 20);
                $table->date('start_date');
                $table->date('end_date');
                $table->unsignedInteger('days')->default(0);
                $table->string('status', 20)->default('pending');
                $table->text('reason')->nullable();
                $table->text('review_notes')->nullable();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('reviewed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'branch_id', 'status', 'start_date'], 'employee_leave_lookup');
            });
        }

        if (
            Schema::hasTable('attendance_records')
            && Schema::hasTable('employee_leave_requests')
            && Schema::hasColumn('attendance_records', 'leave_request_id')
            && ! $this->foreignKeyExists('attendance_records', 'attendance_records_leave_request_id_foreign')
        ) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->foreign('leave_request_id')
                    ->references('id')
                    ->on('employee_leave_requests')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('payroll_runs')) {
            Schema::create('payroll_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->string('payroll_number')->unique();
                $table->string('pay_frequency', 20);
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status', 20)->default('draft');
                $table->unsignedInteger('employee_count')->default(0);
                $table->decimal('gross_total', 14, 2)->default(0);
                $table->decimal('deduction_total', 14, 2)->default(0);
                $table->decimal('advance_recovery_total', 14, 2)->default(0);
                $table->decimal('net_total', 14, 2)->default(0);
                $table->decimal('paid_total', 14, 2)->default(0);
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('finalized_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'branch_id', 'pay_frequency', 'period_start', 'period_end'], 'payroll_run_period_unique');
                $table->index(['company_id', 'status', 'period_end']);
            });
        }

        if (! Schema::hasTable('payroll_items')) {
            Schema::create('payroll_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
                $table->string('employee_number')->nullable();
                $table->string('pay_frequency', 20);
                $table->decimal('pay_rate', 12, 2);
                $table->decimal('standard_hours_per_day', 5, 2);
                $table->decimal('overtime_rate', 12, 2);
                $table->string('short_hours_policy', 20);
                $table->unsignedInteger('scheduled_days')->default(0);
                $table->decimal('payable_days', 8, 2)->default(0);
                $table->unsignedInteger('worked_minutes')->default(0);
                $table->unsignedInteger('regular_minutes')->default(0);
                $table->unsignedInteger('overtime_minutes')->default(0);
                $table->decimal('base_pay', 12, 2)->default(0);
                $table->decimal('overtime_pay', 12, 2)->default(0);
                $table->decimal('bonus_amount', 12, 2)->default(0);
                $table->decimal('deduction_amount', 12, 2)->default(0);
                $table->decimal('advance_recovery_amount', 12, 2)->default(0);
                $table->decimal('gross_pay', 12, 2)->default(0);
                $table->decimal('net_pay', 12, 2)->default(0);
                $table->decimal('paid_amount', 12, 2)->default(0);
                $table->string('status', 20)->default('draft');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['payroll_run_id', 'employee_id']);
                $table->index(['company_id', 'employee_id', 'status']);
            });
        }

        if (! Schema::hasTable('employee_payroll_adjustments')) {
            Schema::create('employee_payroll_adjustments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('payroll_item_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type', 20);
                $table->date('effective_date');
                $table->decimal('amount', 12, 2);
                $table->string('status', 20)->default('pending');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'employee_id', 'status', 'effective_date'], 'employee_adjustment_lookup');
            });
        }

        if (! Schema::hasTable('employee_payments')) {
            Schema::create('employee_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('payroll_item_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('account_id')->constrained()->restrictOnDelete();
                $table->foreignId('money_source_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('payment_number')->unique();
                $table->string('kind', 20);
                $table->date('payment_date');
                $table->decimal('amount', 12, 2);
                $table->string('payment_method', 20);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'employee_id', 'payment_date']);
            });
        }

        if (! Schema::hasTable('employee_advances')) {
            Schema::create('employee_advances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('employee_payment_id')->nullable()->constrained()->nullOnDelete();
                $table->date('advance_date');
                $table->decimal('amount', 12, 2);
                $table->decimal('recovered_amount', 12, 2)->default(0);
                $table->string('status', 20)->default('outstanding');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'employee_id', 'status']);
            });
        }

        if (! Schema::hasTable('payroll_advance_recoveries')) {
            Schema::create('payroll_advance_recoveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_item_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_advance_id')->constrained()->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->timestamps();

                $table->unique(['payroll_item_id', 'employee_advance_id'], 'payroll_advance_recovery_unique');
            });
        }

        if (! Schema::hasTable('employee_ledger_entries')) {
            Schema::create('employee_ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('payroll_item_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('employee_payment_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('employee_advance_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('payroll_adjustment_id')->nullable()->constrained('employee_payroll_adjustments')->nullOnDelete();
                $table->date('entry_date');
                $table->string('type', 30);
                $table->string('direction', 10);
                $table->decimal('amount', 12, 2);
                $table->text('description')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['company_id', 'employee_id', 'entry_date']);
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY COLUMN reference_type ENUM(
                'sale', 'purchase', 'refund', 'expense', 'customer_payment',
                'transfer', 'reconciliation', 'adjustment', 'employee_payment'
            ) NULL");
        }
    }

    public function down(): void
    {
        DB::table('transactions')->where('reference_type', 'employee_payment')->delete();

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY COLUMN reference_type ENUM(
                'sale', 'purchase', 'refund', 'expense', 'customer_payment',
                'transfer', 'reconciliation', 'adjustment'
            ) NULL");
        }

        Schema::dropIfExists('employee_ledger_entries');
        Schema::dropIfExists('payroll_advance_recoveries');
        Schema::dropIfExists('employee_advances');
        Schema::dropIfExists('employee_payments');
        Schema::dropIfExists('employee_payroll_adjustments');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
        if (
            Schema::hasTable('attendance_records')
            && $this->foreignKeyExists('attendance_records', 'attendance_records_leave_request_id_foreign')
        ) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->dropForeign(['leave_request_id']);
            });
        }
        Schema::dropIfExists('employee_leave_requests');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('employee_profiles');
    }

    protected function foreignKeyExists(string $table, string $foreignKey): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $database = DB::getDatabaseName();
        $exists = DB::selectOne(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ?
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = ?',
            [$database, $table, $foreignKey, 'FOREIGN KEY']
        );

        return $exists !== null;
    }
};
