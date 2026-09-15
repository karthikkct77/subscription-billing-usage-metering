<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_successfully_with_default_merchant(): void
    {
        $merchant = Merchant::factory()->create(['name' => 'Acme Analytics']);
        $plan = Plan::factory()->create(['merchant_id' => $merchant->id]);
        $customer = Customer::factory()->create(['merchant_id' => $merchant->id, 'name' => 'Test Customer']);

        $sub = Subscription::factory()->create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'current_plan_id' => $plan->id,
            'status' => 'active',
        ]);

        SubscriptionSegment::factory()->create([
            'subscription_id' => $sub->id,
            'plan_id' => $plan->id,
        ]);

        DailyUsage::factory()->create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'subscription_id' => $sub->id,
            'usage_date' => now()->toDateString(),
            'total_usage_units' => 1500,
        ]);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Acme Analytics');
        $response->assertSee('Test Customer');
        $response->assertSee('1,500');
        $response->assertSee('Daily Usage Trends');
        $response->assertSee('dailyUsageChart');
    }

    public function test_dashboard_renders_specific_merchant_context(): void
    {
        $merchant1 = Merchant::factory()->create(['name' => 'Alpha Merchant']);
        $merchant2 = Merchant::factory()->create(['name' => 'Beta Merchant']);

        $customer1 = Customer::factory()->create(['merchant_id' => $merchant1->id, 'name' => 'Alpha Client']);
        $customer2 = Customer::factory()->create(['merchant_id' => $merchant2->id, 'name' => 'Beta Client']);

        DailyUsage::factory()->create([
            'merchant_id' => $merchant1->id,
            'customer_id' => $customer1->id,
            'usage_date' => now()->toDateString(),
            'total_usage_units' => 500,
        ]);

        DailyUsage::factory()->create([
            'merchant_id' => $merchant2->id,
            'customer_id' => $customer2->id,
            'usage_date' => now()->toDateString(),
            'total_usage_units' => 800,
        ]);

        $response = $this->get("/dashboard?merchant_id={$merchant2->id}");

        $response->assertStatus(200);
        $response->assertSee('Beta Merchant');
        $response->assertSee('Beta Client');
        $response->assertDontSee('Alpha Client');
    }

    public function test_dashboard_handles_non_existent_merchant_id_gracefully(): void
    {
        Merchant::factory()->create(['name' => 'Existing Merchant']);

        $response = $this->get('/dashboard?merchant_id=999999');

        $response->assertStatus(200);
        $response->assertSee('Merchant with ID 999999 was not found.');
    }

    public function test_dashboard_displays_churn_risk_customers(): void
    {
        $now = Carbon::parse('2026-09-15 12:00:00');
        Carbon::setTestNow($now);

        $merchant = Merchant::factory()->create(['name' => 'SaaS Provider']);
        $customer = Customer::factory()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Dropping Customer',
        ]);

        // Prev month usage = 1000, Current month usage = 200 (80% drop)
        DailyUsage::factory()->create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'usage_date' => '2026-08-15',
            'total_usage_units' => 1000,
        ]);

        DailyUsage::factory()->create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'usage_date' => '2026-09-10',
            'total_usage_units' => 200,
        ]);

        $response = $this->get("/dashboard?merchant_id={$merchant->id}");

        $response->assertStatus(200);
        $response->assertSee('Dropping Customer');
        $response->assertSee('-80%');
    }

    public function test_dashboard_displays_empty_state_when_no_trend_data(): void
    {
        $merchant = Merchant::factory()->create(['name' => 'Empty Merchant']);

        $response = $this->get("/dashboard?merchant_id={$merchant->id}");

        $response->assertStatus(200);
        $response->assertSee('No usage data available for this month.');
        $response->assertDontSee('dailyUsageChart');
    }
}
