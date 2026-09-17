<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use App\Services\BillingService;
use App\Services\PlanPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PlanPricingCacheTest extends TestCase
{
    use RefreshDatabase;

    protected Merchant $merchant;

    protected Plan $plan;

    protected PlanPricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = Merchant::factory()->create();
        $this->plan = Plan::factory()->create([
            'merchant_id' => $this->merchant->id,
            'name' => 'Pro Plan',
            'base_price' => 1999.00,
            'included_usage_units' => 2000,
            'overage_rate_per_unit' => 0.15,
        ]);

        $this->pricingService = app(PlanPricingService::class);
    }

    /**
     * Test plan pricing is returned correctly.
     */
    public function test_pricing_is_returned_correctly(): void
    {
        $pricing = $this->pricingService->getPricing($this->plan->id);

        $this->assertNotNull($pricing);
        $this->assertEquals($this->plan->id, $pricing['id']);
        $this->assertEquals(1999.00, $pricing['base_price']);
        $this->assertEquals(2000, $pricing['included_usage_units']);
        $this->assertEquals(0.15, $pricing['overage_rate_per_unit']);
    }

    /**
     * Test first lookup populates cache and subsequent lookup uses cached pricing.
     */
    public function test_first_lookup_populates_cache_and_subsequent_uses_cached(): void
    {
        $cacheKey = $this->pricingService->getCacheKey($this->plan->id);

        $this->assertFalse(Cache::has($cacheKey));

        // First lookup populates cache
        $pricing1 = $this->pricingService->getPricing($this->plan->id);
        $this->assertTrue(Cache::has($cacheKey));

        // Direct DB update bypassing Eloquent observer to verify cache hit
        Plan::where('id', $this->plan->id)->update(['base_price' => 9999.00]);

        // Subsequent lookup returns cached price (1999.00, not 9999.00)
        $pricing2 = $this->pricingService->getPricing($this->plan->id);
        $this->assertEquals(1999.00, $pricing2['base_price']);
    }

    /**
     * Test updating plan pricing invalidates cache.
     */
    public function test_updating_plan_pricing_invalidates_cache(): void
    {
        $cacheKey = $this->pricingService->getCacheKey($this->plan->id);

        // Populate cache
        $this->pricingService->getPricing($this->plan->id);
        $this->assertTrue(Cache::has($cacheKey));

        // Update via service or Eloquent model (triggers PlanObserver)
        $this->plan->update(['base_price' => 2499.00]);

        // Cache must be invalidated
        $this->assertFalse(Cache::has($cacheKey));

        // Next lookup returns updated price
        $updatedPricing = $this->pricingService->getPricing($this->plan->id);
        $this->assertEquals(2499.00, $updatedPricing['base_price']);
    }

    /**
     * Test cache miss falls back to database.
     */
    public function test_cache_miss_falls_back_to_database(): void
    {
        $cacheKey = $this->pricingService->getCacheKey($this->plan->id);
        Cache::forget($cacheKey);

        $this->assertFalse(Cache::has($cacheKey));

        $pricing = $this->pricingService->getPricing($this->plan->id);

        $this->assertNotNull($pricing);
        $this->assertEquals(1999.00, $pricing['base_price']);
    }

    /**
     * Test historical billing does not depend on cached current plan pricing.
     */
    public function test_historical_billing_does_not_depend_on_cached_current_pricing(): void
    {
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59');

        $subscription = Subscription::factory()->create([
            'merchant_id' => $this->merchant->id,
            'current_plan_id' => $this->plan->id,
            'current_period_starts_at' => $cycleStart,
            'current_period_ends_at' => $cycleEnd,
        ]);

        // Historical segment snapshot created at ₹1000 base price
        SubscriptionSegment::factory()->create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->plan->id,
            'starts_at' => $cycleStart,
            'ends_at' => null,
            'snapshot_base_price' => 1000.00,
            'snapshot_included_usage_units' => 500,
            'snapshot_overage_rate_per_unit' => 0.10,
        ]);

        // Populate plan cache with current pricing ₹1999.00
        $this->pricingService->getPricing($this->plan->id);

        // Billing engine generates invoice
        $billingService = app(BillingService::class);
        $invoice = $billingService->generateInvoiceForSubscription($subscription, $cycleStart, $cycleEnd);

        // Invoice total must match historical segment snapshot (₹1000.00), ignoring cached current plan price (₹1999.00)
        $this->assertEquals(1000.00, (float) $invoice->total);
    }
}
