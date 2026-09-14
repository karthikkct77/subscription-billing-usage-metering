<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = fake()->word().' Plan';

        return [
            'merchant_id' => Merchant::factory(),
            'name' => ucfirst($name),
            'code' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'base_price' => fake()->randomElement([29.99, 49.99, 99.99, 199.99]),
            'billing_cycle' => 'monthly',
            'included_usage_units' => fake()->randomElement([1000, 5000, 10000]),
            'overage_rate_per_unit' => 0.0050,
            'is_active' => true,
        ];
    }
}
