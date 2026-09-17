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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EndToEndBillingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test complete lifecycle: Ingestion -> Aggregation -> Billing -> Invoice Items -> Dashboard.
     */
    public function test_complete_end_to_end_billing_lifecycle(): void
    {
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59');

        // 1. Create Merchant & Plan
        $merchant = Merchant::factory()->create(['name' => 'Acme Cloud Services']);
        $plan = Plan::factory()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Scale Plan',
            'code' => 'scale',
            'base_price' => 5000.00,
            'included_usage_units' => 2000,
            'overage_rate_per_unit' => 0.20,
        ]);

        // 2. Create Customer & Active Subscription
        $customer = Customer::factory()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Big Enterprise Client',
        ]);

        $subscription = Subscription::factory()->create([
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'current_plan_id' => $plan->id,
            'current_period_starts_at' => $cycleStart,
            'current_period_ends_at' => $cycleEnd,
            'status' => 'active',
        ]);

        $segment = SubscriptionSegment::factory()->create([
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'starts_at' => $cycleStart,
            'ends_at' => null,
            'snapshot_base_price' => 5000.00,
            'snapshot_included_usage_units' => 2000,
            'snapshot_overage_rate_per_unit' => 0.20,
        ]);

        // 3. Usage Event Ingestion via POST /api/v1/usage
        $event1Payload = [
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'occurred_at' => '2026-09-05T10:00:00Z',
            'units' => 1500,
            'idempotency_key' => 'evt_e2e_001',
        ];

        $event2Payload = [
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'occurred_at' => '2026-09-12T14:30:00Z',
            'units' => 1000,
            'idempotency_key' => 'evt_e2e_002',
        ];

        // Ingest Event 1 -> HTTP 201
        $this->postJson('/api/v1/usage', $event1Payload)->assertStatus(201);

        // Ingest Event 2 -> HTTP 201
        $this->postJson('/api/v1/usage', $event2Payload)->assertStatus(201);

        // Retry Event 1 -> HTTP 200 (Idempotent retry)
        $this->postJson('/api/v1/usage', $event1Payload)->assertStatus(200);

        // Verify usage_events count is 2
        $this->assertEquals(2, UsageEvent::where('merchant_id', $merchant->id)->count());

        // 4. Run Daily Usage Aggregation CLI Command
        $this->artisan('usage:aggregate', [
            '--from' => '2026-09-01',
            '--to' => '2026-09-30',
        ])->assertExitCode(0);

        // Verify pre-aggregated daily_usages records
        $this->assertDatabaseHas('daily_usages', [
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'usage_date' => '2026-09-05',
            'total_usage_units' => 1500,
        ]);

        $this->assertDatabaseHas('daily_usages', [
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'usage_date' => '2026-09-12',
            'total_usage_units' => 1000,
        ]);

        $totalDailyUsage = DailyUsage::where('customer_id', $customer->id)->sum('total_usage_units');
        $this->assertEquals(2500, $totalDailyUsage);

        // 5. Run Cycle-End Billing Generation CLI Command
        $this->artisan('billing:generate', [
            '--date' => '2026-09-30',
        ])->assertExitCode(0);

        // 6. Verify Invoice and Line Items
        $this->assertEquals(1, Invoice::count());

        $invoice = Invoice::first();
        $this->assertEquals($merchant->id, $invoice->merchant_id);
        $this->assertEquals($customer->id, $invoice->customer_id);
        $this->assertEquals($subscription->id, $invoice->subscription_id);

        // Expected math:
        // Base Fee = 5000.00
        // Included allowance = 2000 units
        // Billable overage units = 2500 - 2000 = 500 units
        // Overage amount = 500 * 0.20 = 100.00
        // Total Invoice = 5100.00
        $this->assertEquals(5100.00, (float) $invoice->total);
        $this->assertEquals(5100.00, (float) $invoice->subtotal);

        $this->assertCount(2, $invoice->items);

        $baseItem = $invoice->items->where('type', 'base_fee')->first();
        $this->assertNotNull($baseItem);
        $this->assertEquals(5000.00, (float) $baseItem->amount);

        $overageItem = $invoice->items->where('type', 'overage')->first();
        $this->assertNotNull($overageItem);
        $this->assertEquals(500, $overageItem->quantity);
        $this->assertEquals(100.00, (float) $overageItem->amount);

        // 7. Verify Merchant Analytics Dashboard Endpoint
        $dashboardResponse = $this->getJson("/api/v1/merchants/{$merchant->id}/dashboard");
        $dashboardResponse->assertStatus(200)
            ->assertJsonPath('data.top_customers_by_usage.0.customer_id', $customer->id)
            ->assertJsonPath('data.top_customers_by_usage.0.total_usage_units', 2500);
    }
}
