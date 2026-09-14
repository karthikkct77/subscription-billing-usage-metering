<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->restrictOnDelete();
            $table->string('name');
            $table->string('code');
            $table->decimal('base_price', 15, 2);
            $table->string('billing_cycle')->default('monthly');
            $table->unsignedBigInteger('included_usage_units')->default(0);
            $table->decimal('overage_rate_per_unit', 15, 4)->default(0.0000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['merchant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
