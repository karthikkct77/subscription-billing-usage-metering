<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionSegmentFactory extends Factory
{
    protected $model = SubscriptionSegment::class;

    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'plan_id' => Plan::factory(),
            'starts_at' => now()->startOfMonth(),
            'ends_at' => null,
            'snapshot_base_price' => 29.99,
            'snapshot_included_usage_units' => 1000,
            'snapshot_overage_rate_per_unit' => 0.0050,
        ];
    }
}
