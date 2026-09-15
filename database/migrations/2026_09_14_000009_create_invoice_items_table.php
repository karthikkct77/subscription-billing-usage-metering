<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('subscription_segment_id')->nullable()->constrained('subscription_segments')->nullOnDelete();
            $table->string('type');
            $table->string('description');
            $table->unsignedBigInteger('quantity')->default(1);
            $table->decimal('unit_price', 15, 4)->default(0.0000);
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->timestamps();

            $table->index(['invoice_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
