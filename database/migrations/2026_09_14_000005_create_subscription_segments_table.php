<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->decimal('snapshot_base_price', 15, 2);
            $table->unsignedBigInteger('snapshot_included_usage_units');
            $table->decimal('snapshot_overage_rate_per_unit', 15, 4);
            $table->timestamps();

            $table->index(['subscription_id', 'starts_at', 'ends_at']);
            $table->index(['subscription_id', 'plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_segments');
    }
};
