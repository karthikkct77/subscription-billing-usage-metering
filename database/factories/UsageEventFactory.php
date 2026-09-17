<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Models\UsageEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UsageEventFactory extends Factory
{
    protected $model = UsageEvent::class;

    public function definition(): array
    {
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
            'subscription_segment_id' => null,
            'idempotency_key' => 'evt_'.Str::random(16),
            'usage_units' => fake()->numberBetween(1, 50),
            'occurred_at' => now(),
        ];
    }
}
