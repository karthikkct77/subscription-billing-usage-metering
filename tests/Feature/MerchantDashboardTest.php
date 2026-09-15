<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use App\Services\MerchantDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MerchantDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = Merchant::factory()->create();
    }

    /**
     * Test GET /api/v1/merchants/{id}/dashboard returns 200 with correct structure.
     */
    public function test_dashboard_endpoint_returns_200_with_valid_structure(): void
    {
        $response = $this->getJson("/api/v1/merchants/{$this->merchant->id}/dashboard");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dashboard retrieved successfully.',
                'data' => [
                    'top_customers_by_usage' => [],
                    'projected_overage_revenue' => 0,
                    'churn_risk_customers' => [],
                ],
            ]);
    }

    /**
     * Test non-existent merchant returns 404.
     */
    public function test_non_existent_merchant_returns_404(): void
    {
        $response = $this->getJson('/api/v1/merchants/999999/dashboard');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Merchant not found.',
            ]);
    }

    /**
     * Test top 5 customers ordered by current month usage and limited to 5.
     */
    public function test_top_5_customers_by_usage(): void
    {
        $now = Carbon::parse('2026-09-15 12:00:00');
        $usageDate = '2026-09-14';

        // Create 7 customers with distinct usage amounts
        for ($i = 1; $i <= 7; $i++) {
            $customer = Customer::factory()->create([
                'merchant_id' => $this->merchant->id,
                'name' => "Customer {$i}",
            ]);

            DailyUsage::factory()->create([
                'merchant_id' => $this->merchant->id,
                'customer_id' => $customer->id,
                'usage_date' => $usageDate,
                'total_usage_units' => $i * 100, // 100, 200, 300, 400, 500, 600, 700
            ]);
        }

        $service = app(MerchantDashboardService::class);
        $top = $service->getTopCustomersByUsage($this->merchant->id, $now, 5);

        $this->assertCount(5, $top);
        // First top customer should have 700 units
        $this->assertEquals(700, $top[0]['total_usage_units']);
        $this->assertEquals('Customer 7', $top[0]['name']);
        // Second top customer should have 600 units
        $this->assertEquals(600, $top[1]['total_usage_units']);
        // Fifth top customer should have 300 units
        $this->assertEquals(300, $top[4]['total_usage_units']);
    }

    /**
     * Test tenant isolation prevents customers from another merchant appearing.
     */
    public function test_tenant_isolation_prevents_other_merchant_data(): void
    {
        $otherMerchant = Merchant::factory()->create();
        $otherCustomer = Customer::factory()->create(['merchant_id' => $otherMerchant->id]);

        DailyUsage::factory()->create([
            'merchant_id' => $otherMerchant->id,
            'customer_id' => $otherCustomer->id,
            'usage_date' => '2026-09-14',
            'total_usage_units' => 99999,
        ]);

        $service = app(MerchantDashboardService::class);
        $top = $service->getTopCustomersByUsage($this->merchant->id, Carbon::parse('2026-09-15'), 5);

        $this->assertEmpty($top);
    }

    /**
     * Test projected overage revenue calculation across active subscriptions.
     */
    public function test_projected_overage_revenue_calculation(): void
    {
        // 30 days billing cycle (Sept 1 to Sept 30), current date is Sept 15 (15 elapsed days)
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59');
        $now = Carbon::parse('2026-09-15 12:00:00');

        $plan = Plan::factory()->create([
            'merchant_id' => $this->merchant->id,
            'included_usage_units' => 1000,
            'overage_rate_per_unit' => 0.10, // ₹0.10 per unit overage
        ]);

        $customer = Customer::factory()->create(['merchant_id' => $this->merchant->id]);
        $subscription = Subscription::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $customer->id,
            'current_plan_id' => $plan->id,
            'current_period_starts_at' => $cycleStart,
            'current_period_ends_at' => $cycleEnd,
            'status' => 'active',
        ]);

        SubscriptionSegment::factory()->create([
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'starts_at' => $cycleStart,
            'ends_at' => null,
            'snapshot_included_usage_units' => 1000,
            'snapshot_overage_rate_per_unit' => 0.10,
        ]);

        // In 15 elapsed days, accumulated usage = 1000 units
        // Pace = 1000 units / 15 days = 66.66 units/day
        // Projected full cycle usage = 1000 / 15 * 30 = 2000 units
        // Projected overage = 2000 - 1000 included = 1000 overage units
        // Projected overage revenue = 1000 * 0.10 = ₹100.00
        DailyUsage::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'usage_date' => '2026-09-10',
            'total_usage_units' => 1000,
        ]);

        $service = app(MerchantDashboardService::class);
        $revenue = $service->getProjectedOverageRevenue($this->merchant->id, $now);

        $this->assertEquals(100.00, $revenue);
    }

    /**
     * Test churn risk customers (>50% usage drop month-over-month).
     */
    public function test_churn_risk_customers_detection(): void
    {
        $now = Carbon::parse('2026-09-15 12:00:00'); // Sept 2026 (current), Aug 2026 (previous)

        // Customer 1: Previous month = 1000 units, Current month = 400 units (60% drop -> churn risk)
        $customer1 = Customer::factory()->create(['merchant_id' => $this->merchant->id, 'name' => 'Churn Risk Customer']);
        DailyUsage::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $customer1->id,
            'usage_date' => '2026-08-15',
            'total_usage_units' => 1000,
        ]);
        DailyUsage::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $customer1->id,
            'usage_date' => '2026-09-10',
            'total_usage_units' => 400,
        ]);

        // Customer 2: Previous month = 1000 units, Current month = 600 units (40% drop -> NOT churn risk)
        $customer2 = Customer::factory()->create(['merchant_id' => $this->merchant->id, 'name' => 'Healthy Customer']);
        DailyUsage::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $customer2->id,
            'usage_date' => '2026-08-15',
            'total_usage_units' => 1000,
        ]);
        DailyUsage::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $customer2->id,
            'usage_date' => '2026-09-10',
            'total_usage_units' => 600,
        ]);

        $service = app(MerchantDashboardService::class);
        $churnList = $service->getChurnRiskCustomers($this->merchant->id, $now);

        $this->assertCount(1, $churnList);
        $this->assertEquals($customer1->id, $churnList[0]['customer_id']);
        $this->assertEquals(60.0, $churnList[0]['drop_percentage']);
    }

    /**
     * Test boundary condition: Exactly 50% drop is not returned as churn risk.
     */
    public function test_exact_50_percent_drop_is_not_churn_risk(): void
    {
        $now = Carbon::parse('2026-09-15 12:00:00');

        // Previous = 1000, Current = 500 (Exactly 50% drop -> drop must strictly exceed 50%)
        $customer = Customer::factory()->create(['merchant_id' => $this->merchant->id]);
        DailyUsage::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $customer->id,
            'usage_date' => '2026-08-15',
            'total_usage_units' => 1000,
        ]);
        DailyUsage::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $customer->id,
            'usage_date' => '2026-09-10',
            'total_usage_units' => 500,
        ]);

        $service = app(MerchantDashboardService::class);
        $churnList = $service->getChurnRiskCustomers($this->merchant->id, $now);

        $this->assertEmpty($churnList);
    }

    /**
     * Test zero previous month usage is handled safely and not returned as churn risk.
     */
    public function test_zero_previous_month_usage_is_handled_safely(): void
    {
        $now = Carbon::parse('2026-09-15 12:00:00');

        // Customer with 0 usage in Aug and 0 usage in Sept
        $customer = Customer::factory()->create(['merchant_id' => $this->merchant->id]);

        $service = app(MerchantDashboardService::class);
        $churnList = $service->getChurnRiskCustomers($this->merchant->id, $now);

        $this->assertEmpty($churnList);
    }

    /**
     * Test daily usage trends calculation generates continuous timeline for current month.
     */
    public function test_daily_usage_trends_calculation(): void
    {
        $now = Carbon::parse('2026-09-15 12:00:00');

        $customer = Customer::factory()->create(['merchant_id' => $this->merchant->id]);

        DailyUsage::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $customer->id,
            'usage_date' => '2026-09-01',
            'total_usage_units' => 120,
        ]);

        DailyUsage::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $customer->id,
            'usage_date' => '2026-09-05',
            'total_usage_units' => 350,
        ]);

        $service = app(MerchantDashboardService::class);
        $trends = $service->getDailyUsageTrends($this->merchant->id, $now);

        // 30 days in September
        $this->assertCount(30, $trends);

        // Sept 01 should be 120
        $this->assertEquals('2026-09-01', $trends[0]['date']);
        $this->assertEquals('Sep 01', $trends[0]['label']);
        $this->assertEquals(120, $trends[0]['usage_units']);

        // Sept 02 should be 0 (continuous timeline)
        $this->assertEquals('2026-09-02', $trends[1]['date']);
        $this->assertEquals(0, $trends[1]['usage_units']);

        // Sept 05 should be 350
        $this->assertEquals('2026-09-05', $trends[4]['date']);
        $this->assertEquals(350, $trends[4]['usage_units']);
    }
}

