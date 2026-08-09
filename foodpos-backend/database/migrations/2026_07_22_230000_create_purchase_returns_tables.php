<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->decimal('quantity_returned', 12, 4)->default(0)->after('quantity');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->decimal('returned_amount', 12, 2)->default(0)->after('paid_amount');
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('return_number');
            $table->date('return_date');
            $table->date('business_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('settlement_type', 32)->default('reduce_payable');
            $table->decimal('payable_reduction_amount', 12, 2)->default(0);
            $table->decimal('credit_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'return_number']);
            $table->index(['company_id', 'return_date']);
            $table->index(['purchase_id']);
            $table->index(['supplier_id']);
            $table->index(['shift_id']);
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained('purchase_items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_price', 12, 4)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->decimal('stock_reversed_qty', 12, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['purchase_return_id']);
            $table->index(['purchase_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('returned_amount');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn('quantity_returned');
        });
    }
};
