<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('quantity_refunded', 10, 2)->default(0)->after('quantity');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->text('management_notes')->nullable()->after('notes');
        });

        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('subtotal_refund', 10, 2)->default(0);
            $table->decimal('tax_refund', 10, 2)->default(0);
            $table->decimal('total_refund', 10, 2)->default(0);
            $table->text('notes');
            $table->timestamps();

            $table->index('order_id');
            $table->index('created_at');
        });

        Schema::create('order_refund_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_refund_id')->constrained('order_refunds')->onDelete('cascade');
            $table->foreignId('order_item_id')->constrained('order_items')->onDelete('cascade');
            $table->decimal('quantity', 10, 2);
            $table->decimal('refund_subtotal', 10, 2);
            $table->decimal('refund_tax', 10, 2)->default(0);
            $table->boolean('restock_inventory')->default(false);
            $table->text('line_notes')->nullable();
            $table->timestamps();

            $table->index('order_refund_id');
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_refund_lines');
        Schema::dropIfExists('order_refunds');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('management_notes');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('quantity_refunded');
        });
    }
};
