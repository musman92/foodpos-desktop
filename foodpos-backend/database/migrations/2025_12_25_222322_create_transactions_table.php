<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('account_id')->constrained()->onDelete('restrict');
            $table->decimal('amount', 10, 2);
            $table->enum('type', ['in', 'out']);
            $table->enum('payment_method', ['cash', 'transfer', 'card', 'online']);
            $table->enum('reference_type', ['sale', 'purchase', 'refund', 'expense'])->nullable();
            $table->date('date');
            $table->unsignedBigInteger('ref_id')->nullable(); // Reference to order, purchase, etc.
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('branch_id');
            $table->index('account_id');
            $table->index('date');
            $table->index(['reference_type', 'ref_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
