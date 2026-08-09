<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_kitchen_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->unsignedInteger('last_kot_number')->default(0);
            $table->unsignedInteger('last_token_number')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'business_date']);
        });

        Schema::create('kitchen_kots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('kot_number');
            $table->unsignedInteger('token_number');
            $table->enum('type', ['full', 'add', 'void'])->default('full');
            $table->json('lines');
            $table->boolean('is_reprint')->default(false);
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'kot_number']);
            $table->index(['branch_id', 'token_number']);
            $table->index('order_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->json('kitchen_cart_snapshot')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('kitchen_cart_snapshot');
        });

        Schema::dropIfExists('kitchen_kots');
        Schema::dropIfExists('branch_kitchen_counters');
    }
};
