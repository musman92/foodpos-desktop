<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_addons', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('company_id');
            $table->string('type', 20)->default('none')->after('price');
            $table->decimal('cost', 10, 2)->default(0)->after('type');
            $table->boolean('track_inventory')->default(false)->after('cost');
            $table->foreignId('menu_item_id')->nullable()->after('track_inventory')->constrained('menu_items')->nullOnDelete();

            $table->index(['company_id', 'code']);
        });

        Schema::create('product_addon_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_addon_id')->constrained('product_addons')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->string('unit_id')->nullable();
            $table->decimal('waste_percentage', 8, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('product_addon_id');
            $table->index('ingredient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_addon_recipes');

        Schema::table('product_addons', function (Blueprint $table) {
            $table->dropForeign(['menu_item_id']);
            $table->dropIndex(['company_id', 'code']);
            $table->dropColumn(['code', 'type', 'cost', 'track_inventory', 'menu_item_id']);
        });
    }
};
