<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('money_source_fund_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_money_source_id')->constrained('money_sources')->restrictOnDelete();
            $table->foreignId('to_money_source_id')->constrained('money_sources')->restrictOnDelete();
            $table->string('movement_type', 32)->default('owner_withdrawal');
            $table->decimal('amount', 15, 2);
            $table->date('movement_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'movement_date'], 'msfm_company_date_idx');
            $table->index(['branch_id', 'movement_date'], 'msfm_branch_date_idx');
            $table->index(['from_money_source_id', 'movement_date'], 'msfm_from_source_date_idx');
            $table->index(['to_money_source_id', 'movement_date'], 'msfm_to_source_date_idx');
            $table->index('movement_type', 'msfm_movement_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('money_source_fund_movements');
    }
};
