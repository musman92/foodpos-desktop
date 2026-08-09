<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['points', 'tiered'])->default('points');
            $table->decimal('points_per_currency', 10, 2)->default(1); // e.g., 1 point per $1
            $table->decimal('currency_per_point', 10, 2)->default(0.01); // Redemption rate
            $table->json('tier_rules')->nullable(); // For tiered discounts
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_programs');
    }
};

