<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Merchant;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailyUsageFactory extends Factory
{
    protected $model = DailyUsage::class;

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
            'usage_date' => now()->toDateString(),
            'total_usage_units' => fake()->numberBetween(100, 1000),
        ];
    }
}
