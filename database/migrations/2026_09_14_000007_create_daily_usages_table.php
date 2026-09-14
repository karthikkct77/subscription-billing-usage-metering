<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('subscription_id')->constrained('subscriptions')->restrictOnDelete();
            $table->foreignId('subscription_segment_id')->nullable()->constrained('subscription_segments')->nullOnDelete();
            $table->date('usage_date');
            $table->unsignedBigInteger('total_usage_units')->default(0);
            $table->timestamps();

            $table->unique(['subscription_id', 'subscription_segment_id', 'usage_date'], 'daily_usage_sub_seg_date_unique');
            $table->index(['merchant_id', 'usage_date']);
            $table->index(['merchant_id', 'customer_id', 'usage_date']);
            $table->index(['customer_id', 'usage_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_usages');
    }
};
