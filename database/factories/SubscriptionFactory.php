<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $startsAt = now()->startOfMonth();

        return [
            'merchant_id' => Merchant::factory(),
            'customer_id' => function (array $attributes) {
                return Customer::factory()->create(['merchant_id' => $attributes['merchant_id']])->id;
            },
            'current_plan_id' => function (array $attributes) {
                return Plan::factory()->create(['merchant_id' => $attributes['merchant_id']])->id;
            },
            'starts_at' => $startsAt,
            'ends_at' => null,
            'current_period_starts_at' => $startsAt,
            'current_period_ends_at' => $startsAt->copy()->addMonth(),
            'status' => 'active',
        ];
    }
}
