<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use App\Models\UsageEvent;
use App\Services\BillingService;
use App\Services\MerchantDashboardService;
use App\Services\UsageAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MultiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Merchant $merchantA;

    protected Merchant $merchantB;

    protected Customer $customerA;

    protected Customer $customerB;

    protected Subscription $subscriptionA;

    protected Subscription $subscriptionB;

    protected function setUp(): void
    {
        parent::setUp();

        // Merchant A Setup
        $this->merchantA = Merchant::factory()->create(['name' => 'Merchant A']);
        $this->customerA = Customer::factory()->create(['merchant_id' => $this->merchantA->id, 'name' => 'Customer A']);
        $planA = Plan::factory()->create(['merchant_id' => $this->merchantA->id, 'base_price' => 1000]);
        $this->subscriptionA = Subscription::factory()->create([
            'merchant_id' => $this->merchantA->id,
            'customer_id' => $this->customerA->id,
            'current_plan_id' => $planA->id,
            'status' => 'active',
        ]);
        SubscriptionSegment::factory()->create(['subscription_id' => $this->subscriptionA->id, 'plan_id' => $planA->id]);

        // Merchant B Setup
        $this->merchantB = Merchant::factory()->create(['name' => 'Merchant B']);
        $this->customerB = Customer::factory()->create(['merchant_id' => $this->merchantB->id, 'name' => 'Customer B']);
        $planB = Plan::factory()->create(['merchant_id' => $this->merchantB->id, 'base_price' => 2000]);
        $this->subscriptionB = Subscription::factory()->create([
            'merchant_id' => $this->merchantB->id,
            'customer_id' => $this->customerB->id,
            'current_plan_id' => $planB->id,
            'status' => 'active',
        ]);
        SubscriptionSegment::factory()->create(['subscription_id' => $this->subscriptionB->id, 'plan_id' => $planB->id]);
    }

    /**
     * Test API rejects usage ingestion when customer belongs to a different merchant.
     */
    public function test_api_rejects_cross_tenant_customer_mismatch(): void
    {
        $payload = [
            'merchant_id' => $this->merchantA->id,
            'customer_id' => $this->customerB->id, // Mismatch!
            'occurred_at' => now()->toIso8601String(),
            'units' => 100,
            'idempotency_key' => 'evt_cross_tenant_test',
        ];

        $response = $this->postJson('/api/v1/usage', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('errors.customer_id.0', 'The selected customer does not belong to the specified merchant.');

        $this->assertEquals(0, UsageEvent::count());
    }

    /**
     * Test tenant-scoped aggregation runs leave other merchant's data untouched.
     */
    public function test_aggregation_service_respects_merchant_isolation(): void
    {
        $date = '2026-09-14';

        UsageEvent::factory()->create([
            'merchant_id' => $this->merchantA->id,
            'customer_id' => $this->customerA->id,
            'subscription_id' => $this->subscriptionA->id,
            'usage_units' => 50,
            'occurred_at' => "{$date} 10:00:00",
        ]);

        UsageEvent::factory()->create([
            'merchant_id' => $this->merchantB->id,
            'customer_id' => $this->customerB->id,
            'subscription_id' => $this->subscriptionB->id,
            'usage_units' => 999,
            'occurred_at' => "{$date} 10:00:00",
        ]);

        $service = app(UsageAggregationService::class);
        // Aggregate ONLY Merchant A
        $service->aggregate(merchantId: $this->merchantA->id);

        $this->assertEquals(1, DailyUsage::count());
        $this->assertDatabaseHas('daily_usages', [
            'merchant_id' => $this->merchantA->id,
            'customer_id' => $this->customerA->id,
            'total_usage_units' => 50,
        ]);
        $this->assertDatabaseMissing('daily_usages', [
            'merchant_id' => $this->merchantB->id,
        ]);
    }

    /**
     * Test billing generation for Merchant A never bills Merchant B subscriptions.
     */
    public function test_billing_engine_respects_merchant_isolation(): void
    {
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59');

        $billingService = app(BillingService::class);
        $invoiceA = $billingService->generateInvoiceForSubscription($this->subscriptionA, $cycleStart, $cycleEnd);

        $this->assertEquals(1, Invoice::count());
        $this->assertEquals($this->merchantA->id, $invoiceA->merchant_id);
        $this->assertDatabaseMissing('invoices', [
            'merchant_id' => $this->merchantB->id,
        ]);
    }

    /**
     * Test merchant dashboard never leaks data from another merchant.
     */
    public function test_merchant_dashboard_never_leaks_another_merchants_data(): void
    {
        $date = '2026-09-14';

        // Merchant A usage
        DailyUsage::factory()->create([
            'merchant_id' => $this->merchantA->id,
            'customer_id' => $this->customerA->id,
            'usage_date' => $date,
            'total_usage_units' => 100,
        ]);

        // Merchant B usage (much larger)
        DailyUsage::factory()->create([
            'merchant_id' => $this->merchantB->id,
            'customer_id' => $this->customerB->id,
            'usage_date' => $date,
            'total_usage_units' => 999999,
        ]);

        $dashboardService = app(MerchantDashboardService::class);
        $dashboardA = $dashboardService->getDashboardData($this->merchantA->id, Carbon::parse('2026-09-15'));

        // Verify Merchant A dashboard contains ONLY Customer A
        $topA = $dashboardA['top_customers_by_usage'];
        $this->assertCount(1, $topA);
        $this->assertEquals($this->customerA->id, $topA[0]['customer_id']);
        $this->assertEquals(100, $topA[0]['total_usage_units']);

        // Verify HTTP dashboard route for Merchant A
        $response = $this->getJson("/api/v1/merchants/{$this->merchantA->id}/dashboard");
        $response->assertStatus(200)
            ->assertJsonPath('data.top_customers_by_usage.0.customer_id', $this->customerA->id);
    }
}
