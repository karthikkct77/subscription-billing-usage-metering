<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use App\Models\UsageEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test merchant can have multiple plans.
     */
    public function test_merchant_can_have_multiple_plans(): void
    {
        $merchant = Merchant::factory()->create();

        $plan1 = Plan::factory()->create(['merchant_id' => $merchant->id, 'code' => 'basic']);
        $plan2 = Plan::factory()->create(['merchant_id' => $merchant->id, 'code' => 'pro']);

        $this->assertCount(2, $merchant->plans);
        $this->assertTrue($merchant->plans->contains($plan1));
        $this->assertTrue($merchant->plans->contains($plan2));
    }

    /**
     * Test customer belongs to merchant and maintains tenant scoping.
     */
    public function test_customer_belongs_to_merchant(): void
    {
        $merchant = Merchant::factory()->create();
        $customer = Customer::factory()->create(['merchant_id' => $merchant->id]);

        $this->assertEquals($merchant->id, $customer->merchant->id);
        $this->assertTrue($merchant->customers->contains($customer));
    }

    /**
     * Test subscription belongs to customer, merchant, and current plan.
     */
    public function test_subscription_belongs_to_customer_and_merchant(): void
    {
        $subscription = Subscription::factory()->create();

        $this->assertNotNull($subscription->merchant);
        $this->assertNotNull($subscription->customer);
        $this->assertNotNull($subscription->currentPlan);
        $this->assertEquals($subscription->merchant_id, $subscription->customer->merchant_id);
    }

    /**
     * Test subscription supports multiple historical segments for mid-cycle plan changes.
     */
    public function test_subscription_supports_multiple_historical_segments_with_pricing_snapshots(): void
    {
        $merchant = Merchant::factory()->create();
        $planA = Plan::factory()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Plan A',
            'base_price' => 29.99,
            'included_usage_units' => 1000,
            'overage_rate_per_unit' => 0.0050,
        ]);
        $planB = Plan::factory()->create([
            'merchant_id' => $merchant->id,
            'name' => 'Plan B',
            'base_price' => 99.99,
            'included_usage_units' => 5000,
            'overage_rate_per_unit' => 0.0025,
        ]);

        $subscription = Subscription::factory()->create([
            'merchant_id' => $merchant->id,
            'current_plan_id' => $planB->id,
        ]);

        // Segment 1 (Plan A: Sept 1 to Sept 15)
        $segment1 = SubscriptionSegment::factory()->create([
            'subscription_id' => $subscription->id,
            'plan_id' => $planA->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => '2026-09-15 23:59:59',
            'snapshot_base_price' => $planA->base_price,
            'snapshot_included_usage_units' => $planA->included_usage_units,
            'snapshot_overage_rate_per_unit' => $planA->overage_rate_per_unit,
        ]);

        // Segment 2 (Plan B: Sept 16 to Sept 30)
        $segment2 = SubscriptionSegment::factory()->create([
            'subscription_id' => $subscription->id,
            'plan_id' => $planB->id,
            'starts_at' => '2026-09-16 00:00:00',
            'ends_at' => '2026-09-30 23:59:59',
            'snapshot_base_price' => $planB->base_price,
            'snapshot_included_usage_units' => $planB->included_usage_units,
            'snapshot_overage_rate_per_unit' => $planB->overage_rate_per_unit,
        ]);

        $this->assertCount(2, $subscription->segments);

        // Edit Plan A pricing later - historical segment snapshot remains intact
        $planA->update(['base_price' => 49.99]);
        $this->assertEquals(29.99, $segment1->fresh()->snapshot_base_price);
    }

    /**
     * Test duplicate idempotency key per merchant is rejected at database level.
     */
    public function test_duplicate_usage_idempotency_key_is_rejected(): void
    {
        $merchant = Merchant::factory()->create();
        $idempotencyKey = 'evt_test_12345';

        UsageEvent::factory()->create([
            'merchant_id' => $merchant->id,
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->expectException(QueryException::class);

        UsageEvent::factory()->create([
            'merchant_id' => $merchant->id,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    /**
     * Test daily usage aggregate uniqueness constraint prevents duplicates.
     */
    public function test_daily_usage_uniqueness_prevents_duplicate_aggregates(): void
    {
        $subscription = Subscription::factory()->create();
        $segment = SubscriptionSegment::factory()->create(['subscription_id' => $subscription->id]);
        $date = '2026-09-14';

        DailyUsage::factory()->create([
            'merchant_id' => $subscription->merchant_id,
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
            'subscription_segment_id' => $segment->id,
            'usage_date' => $date,
            'total_usage_units' => 500,
        ]);

        $this->expectException(QueryException::class);

        DailyUsage::factory()->create([
            'merchant_id' => $subscription->merchant_id,
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
            'subscription_segment_id' => $segment->id,
            'usage_date' => $date,
            'total_usage_units' => 300,
        ]);
    }

    /**
     * Test invoice can contain multiple items.
     */
    public function test_invoice_can_contain_multiple_items(): void
    {
        $invoice = Invoice::factory()->create();

        $item1 = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'type' => 'base_fee',
            'amount' => 29.99,
        ]);

        $item2 = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'type' => 'overage',
            'amount' => 15.50,
        ]);

        $this->assertCount(2, $invoice->fresh()->items);
        $this->assertTrue($invoice->items->contains($item1));
        $this->assertTrue($invoice->items->contains($item2));
    }
}
