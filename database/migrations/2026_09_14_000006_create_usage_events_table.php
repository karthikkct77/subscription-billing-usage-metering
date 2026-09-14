<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('subscription_segment_id')->nullable()->constrained('subscription_segments')->nullOnDelete();
            $table->string('idempotency_key');
            $table->unsignedBigInteger('usage_units');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['merchant_id', 'idempotency_key']);
            $table->index(['merchant_id', 'occurred_at']);
            $table->index(['merchant_id', 'customer_id', 'occurred_at']);
            $table->index(['subscription_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
