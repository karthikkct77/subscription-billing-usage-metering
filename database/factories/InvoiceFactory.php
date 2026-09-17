<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $start = now()->subMonth()->startOfMonth();

        return [
            'merchant_id' => Merchant::factory(),
            'customer_id' => function (array $attributes) {
                return Customer::factory()->create(['merchant_id' => $attributes['merchant_id']])->id;
            },
            'subscription_id' => function (array $attributes) {
                return Subscription::factory()->create([
                    'merchant_id' => $attributes['merchant_id'],
                    'customer_id' => $attributes['customer_id'],
                ])->id;
            },
            'invoice_number' => 'INV-'.fake()->unique()->numberBetween(100000, 999999),
            'period_starts_at' => $start,
            'period_ends_at' => $start->copy()->addMonth(),
            'status' => 'draft',
            'subtotal' => 49.99,
            'total' => 49.99,
            'due_at' => now()->addDays(14),
            'paid_at' => null,
        ];
    }
}
