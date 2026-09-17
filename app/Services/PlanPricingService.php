<?php

namespace App\Services;

use App\Models\Plan;
use Exception;
use Illuminate\Support\Facades\Cache;

class PlanPricingService
{
    /**
     * Cache TTL in seconds (24 hours).
     */
    public int $ttl = 86400;

    /**
     * Get predictable cache key for a plan's pricing.
     */
    public function getCacheKey(int $planId): string
    {
        return sprintf('plan:%d:pricing', $planId);
    }

    /**
     * Retrieve cached pricing data for a plan, falling back to database on cache miss or cache error.
     */
    public function getPricing(int $planId): ?array
    {
        $cacheKey = $this->getCacheKey($planId);

        try {
            return Cache::remember($cacheKey, $this->ttl, function () use ($planId) {
                return $this->fetchFromDatabase($planId);
            });
        } catch (Exception $e) {
            // Fallback gracefully to direct database lookup if cache store is unreachable
            return $this->fetchFromDatabase($planId);
        }
    }

    /**
     * Update plan pricing and explicitly invalidate its cache.
     */
    public function updatePlanPricing(Plan $plan, array $data): Plan
    {
        $plan->update($data);
        $this->invalidateCache($plan->id);

        return $plan;
    }

    /**
     * Invalidate cached pricing data for a plan.
     */
    public function invalidateCache(int $planId): void
    {
        try {
            Cache::forget($this->getCacheKey($planId));
        } catch (Exception $e) {
            // Ignore cache store failure during invalidation
        }
    }

    /**
     * Direct database lookup for plan pricing representation.
     */
    protected function fetchFromDatabase(int $planId): ?array
    {
        $plan = Plan::find($planId);

        if (! $plan) {
            return null;
        }

        return [
            'id' => (int) $plan->id,
            'merchant_id' => (int) $plan->merchant_id,
            'name' => (string) $plan->name,
            'code' => (string) $plan->code,
            'base_price' => (float) $plan->base_price,
            'billing_cycle' => (string) $plan->billing_cycle,
            'included_usage_units' => (int) $plan->included_usage_units,
            'overage_rate_per_unit' => (float) $plan->overage_rate_per_unit,
            'is_active' => (bool) $plan->is_active,
        ];
    }
}
