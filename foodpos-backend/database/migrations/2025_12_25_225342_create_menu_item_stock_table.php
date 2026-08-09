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
        Schema::create('menu_item_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->foreignId('menu_item_id')->constrained()->onDelete('cascade');
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('unit_price', 10, 2)->default(0); // Purchase price for this batch
            $table->date('expiry_date')->nullable();
            $table->timestamp('last_restocked_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'menu_item_id', 'unit_price', 'expiry_date'], 'menu_item_stock_batch_unique'); // Same item, same price, same expiry = same batch
            $table->index('branch_id');
            $table->index('menu_item_id');
            $table->index('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_item_stock');
    }
};
