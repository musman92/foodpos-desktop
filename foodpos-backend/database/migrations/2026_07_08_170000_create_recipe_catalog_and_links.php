<?php

use App\Models\MenuItem;
use App\Models\Variant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Introduce catalog Recipes (header + items) and menu-item links.
 * Existing ingredient lines are renamed (not wiped) and backfilled into the catalog.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Preserve existing BOM lines under a clearer table name.
        if (Schema::hasTable('recipes') && ! Schema::hasTable('menu_item_recipe_lines')) {
            Schema::rename('recipes', 'menu_item_recipe_lines');
        }

        // 2) Catalog recipe headers.
        if (! Schema::hasTable('recipes')) {
            Schema::create('recipes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code', 50)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index('company_id');
                $table->index(['company_id', 'is_active']);
                $table->unique(['company_id', 'code']);
            });
        }

        // 3) Catalog recipe ingredient lines.
        if (! Schema::hasTable('recipe_items')) {
            Schema::create('recipe_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
                $table->decimal('quantity', 10, 2);
                $table->string('unit_id')->nullable();
                $table->decimal('waste_percentage', 5, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['recipe_id', 'ingredient_id']);
                $table->index('recipe_id');
                $table->index('ingredient_id');
            });
        }

        // 4) Default recipe on menu item.
        if (Schema::hasTable('menu_items') && ! Schema::hasColumn('menu_items', 'default_recipe_id')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->foreignId('default_recipe_id')
                    ->nullable()
                    ->after('type')
                    ->constrained('recipes')
                    ->nullOnDelete();
            });
        }

        // 5) Per-option recipe links (variant option, not whole variant row).
        if (! Schema::hasTable('menu_item_variant_recipes')) {
            Schema::create('menu_item_variant_recipes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
                $table->foreignId('variant_id')->constrained()->cascadeOnDelete();
                $table->string('option_name', 255);
                $table->foreignId('recipe_id')->constrained('recipes')->restrictOnDelete();
                $table->timestamps();

                $table->unique(['menu_item_id', 'variant_id', 'option_name'], 'mivr_item_variant_option_unique');
                $table->index('recipe_id');
            });
        }

        $this->backfillFromLegacyLines();
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_variant_recipes');

        if (Schema::hasColumn('menu_items', 'default_recipe_id')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('default_recipe_id');
            });
        }

        Schema::dropIfExists('recipe_items');
        Schema::dropIfExists('recipes');

        if (Schema::hasTable('menu_item_recipe_lines') && ! Schema::hasTable('recipes')) {
            Schema::rename('menu_item_recipe_lines', 'recipes');
        }
    }

    private function backfillFromLegacyLines(): void
    {
        if (! Schema::hasTable('menu_item_recipe_lines')) {
            return;
        }

        $alreadyLinked = DB::table('menu_items')
            ->whereNotNull('default_recipe_id')
            ->exists()
            || DB::table('menu_item_variant_recipes')->exists();

        if ($alreadyLinked) {
            return;
        }

        $groups = DB::table('menu_item_recipe_lines')
            ->select('menu_item_id', 'recipe_scope')
            ->distinct()
            ->orderBy('menu_item_id')
            ->orderBy('recipe_scope')
            ->get();

        $menuItems = MenuItem::withoutGlobalScopes()
            ->withTrashed()
            ->get()
            ->keyBy('id');

        $variants = Variant::withoutGlobalScopes()
            ->withTrashed()
            ->get()
            ->keyBy('id');

        $codeSeqByCompany = [];

        foreach ($groups as $group) {
            $menuItem = $menuItems->get($group->menu_item_id);
            if (! $menuItem) {
                continue;
            }

            $companyId = (int) $menuItem->company_id;
            $scope = (string) ($group->recipe_scope ?? '');
            $name = $this->recipeNameFor($menuItem->name, $scope, $variants);

            if (! isset($codeSeqByCompany[$companyId])) {
                $codeSeqByCompany[$companyId] = $this->nextCodeNumber($companyId);
            }
            $code = 'R'.str_pad((string) $codeSeqByCompany[$companyId], 2, '0', STR_PAD_LEFT);
            $codeSeqByCompany[$companyId]++;

            $recipeId = DB::table('recipes')->insertGetId([
                'company_id' => $companyId,
                'name' => $name,
                'code' => $code,
                'description' => 'Migrated from menu item BOM',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $lines = DB::table('menu_item_recipe_lines')
                ->where('menu_item_id', $group->menu_item_id)
                ->where('recipe_scope', $scope)
                ->get();

            foreach ($lines as $line) {
                $exists = DB::table('recipe_items')
                    ->where('recipe_id', $recipeId)
                    ->where('ingredient_id', $line->ingredient_id)
                    ->exists();
                if ($exists) {
                    continue;
                }

                DB::table('recipe_items')->insert([
                    'recipe_id' => $recipeId,
                    'ingredient_id' => $line->ingredient_id,
                    'quantity' => $line->quantity,
                    'unit_id' => $line->unit_id,
                    'waste_percentage' => $line->waste_percentage ?? 0,
                    'notes' => $line->notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($scope === '') {
                DB::table('menu_items')
                    ->where('id', $menuItem->id)
                    ->whereNull('default_recipe_id')
                    ->update(['default_recipe_id' => $recipeId, 'updated_at' => now()]);

                continue;
            }

            $parts = explode(':', $scope, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $variantId = (int) $parts[0];
            $optionName = $parts[1];
            if ($variantId <= 0 || $optionName === '') {
                continue;
            }

            $exists = DB::table('menu_item_variant_recipes')
                ->where('menu_item_id', $menuItem->id)
                ->where('variant_id', $variantId)
                ->where('option_name', $optionName)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('menu_item_variant_recipes')->insert([
                'menu_item_id' => $menuItem->id,
                'variant_id' => $variantId,
                'option_name' => $optionName,
                'recipe_id' => $recipeId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function recipeNameFor(string $menuItemName, string $scope, $variants): string
    {
        if ($scope === '') {
            return $menuItemName.' — Default';
        }

        $parts = explode(':', $scope, 2);
        if (count($parts) !== 2) {
            return $menuItemName.' — '.$scope;
        }

        $variant = $variants->get((int) $parts[0]);
        $variantName = $variant?->name ?? 'Variant';

        return $menuItemName.' — '.$variantName.': '.$parts[1];
    }

    private function nextCodeNumber(int $companyId): int
    {
        $codes = DB::table('recipes')
            ->where('company_id', $companyId)
            ->whereNotNull('code')
            ->pluck('code');

        $max = 0;
        foreach ($codes as $code) {
            if (preg_match('/^R(\d+)$/i', trim((string) $code), $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max + 1;
    }
};
