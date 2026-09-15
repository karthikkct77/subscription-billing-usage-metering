<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('merchants')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('subscription_id')->constrained('subscriptions')->restrictOnDelete();
            $table->string('invoice_number')->unique();
            $table->timestamp('period_starts_at');
            $table->timestamp('period_ends_at');
            $table->string('status')->default('draft');
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('total', 15, 2)->default(0.00);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'period_starts_at', 'period_ends_at'], 'invoices_sub_period_unique');
            $table->index(['merchant_id', 'period_starts_at', 'period_ends_at']);
            $table->index(['customer_id', 'period_starts_at']);
            $table->index(['subscription_id', 'period_starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
