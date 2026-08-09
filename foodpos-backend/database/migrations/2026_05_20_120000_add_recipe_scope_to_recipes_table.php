<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropUnique(['menu_item_id', 'ingredient_id']);
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->string('recipe_scope', 120)->default('')->after('menu_item_id');
            $table->unique(['menu_item_id', 'ingredient_id', 'recipe_scope'], 'recipes_item_ingredient_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropUnique('recipes_item_ingredient_scope_unique');
            $table->dropColumn('recipe_scope');
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->unique(['menu_item_id', 'ingredient_id']);
        });
    }
};
