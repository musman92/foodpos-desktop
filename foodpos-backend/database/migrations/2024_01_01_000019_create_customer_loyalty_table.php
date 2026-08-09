<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_loyalty', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('customer_phone')->index(); // Primary identifier
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->decimal('total_points', 10, 2)->default(0);
            $table->decimal('redeemed_points', 10, 2)->default(0);
            $table->string('tier')->nullable(); // For tiered programs
            $table->decimal('lifetime_spent', 10, 2)->default(0);
            $table->integer('total_orders')->default(0);
            $table->timestamp('last_order_at')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->unique(['company_id', 'customer_phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_loyalty');
    }
};

