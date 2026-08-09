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
        if (!Schema::hasTable('purchase_items')) {
            Schema::create('purchase_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_id')->constrained()->onDelete('cascade');
                $table->enum('item_type', ['ingredient', 'menu_item']);
                $table->unsignedBigInteger('item_id'); // ingredient_id or menu_item_id
                $table->decimal('quantity', 10, 2);
                $table->string('unit_id')->nullable(); // Unit from config
                $table->decimal('unit_price', 10, 2);
                $table->decimal('total_price', 10, 2);
                $table->date('expiry_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('purchase_id');
                $table->index(['item_type', 'item_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
