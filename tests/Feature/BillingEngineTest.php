<?php

namespace Tests\Feature;

use App\Jobs\ProcessSubscriptionBilling;
use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BillingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Merchant $merchant;

    protected Customer $customer;

    protected Plan $planA;

    protected Plan $planB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = Merchant::factory()->create();
        $this->customer = Customer::factory()->create(['merchant_id' => $this->merchant->id]);

        // Plan A: ₹3000 base, 1000 included units, ₹0.10 overage/unit
        $this->planA = Plan::factory()->create([
            'merchant_id' => $this->merchant->id,
            'name' => 'Plan A',
            'base_price' => 3000.00,
            'included_usage_units' => 1000,
            'overage_rate_per_unit' => 0.10,
        ]);

        // Plan B: ₹6000 base, 5000 included units, ₹0.05 overage/unit
        $this->planB = Plan::factory()->create([
            'merchant_id' => $this->merchant->id,
            'name' => 'Plan B',
            'base_price' => 6000.00,
            'included_usage_units' => 5000,
            'overage_rate_per_unit' => 0.05,
        ]);
    }

    /**
     * Test full cycle subscription charges full base price.
     */
    public function test_full_cycle_subscription_charges_full_base_price(): void
    {
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59');

        $subscription = Subscription::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'current_plan_id' => $this->planA->id,
            'current_period_starts_at' => $cycleStart,
            'current_period_ends_at' => $cycleEnd,
        ]);

        $segment = SubscriptionSegment::factory()->create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->planA->id,
            'starts_at' => $cycleStart,
            'ends_at' => null,
            'snapshot_base_price' => 3000.00,
            'snapshot_included_usage_units' => 1000,
            'snapshot_overage_rate_per_unit' => 0.10,
        ]);

        $service = app(BillingService::class);
        $invoice = $service->generateInvoiceForSubscription($subscription, $cycleStart, $cycleEnd);

        $this->assertEquals(3000.00, (float) $invoice->total);
        $this->assertCount(1, $invoice->items);
        $this->assertEquals(3000.00, (float) $invoice->items->first()->amount);
        $this->assertEquals('base_fee', $invoice->items->first()->type);
    }

    /**
     * Test mid-cycle subscription start is prorated correctly.
     */
    public function test_mid_cycle_subscription_start_is_prorated(): void
    {
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59'); // 30 days total

        // Subscription starts on Sept 16 (15 active days out of 30)
        $subStart = Carbon::parse('2026-09-16 00:00:00');

        $subscription = Subscription::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'current_plan_id' => $this->planA->id,
            'current_period_starts_at' => $cycleStart,
            'current_period_ends_at' => $cycleEnd,
        ]);

        SubscriptionSegment::factory()->create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->planA->id,
            'starts_at' => $subStart,
            'ends_at' => null,
            'snapshot_base_price' => 3000.00,
            'snapshot_included_usage_units' => 1000,
            'snapshot_overage_rate_per_unit' => 0.10,
        ]);

        $service = app(BillingService::class);
        $invoice = $service->generateInvoiceForSubscription($subscription, $cycleStart, $cycleEnd);

        // 15 days active out of 30 days = 3000 * 15 / 30 = 1500.00
        $this->assertEquals(1500.00, (float) $invoice->total);
    }

    /**
     * Test one day segment proration.
     */
    public function test_one_day_segment_proration(): void
    {
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59'); // 30 days

        $subscription = Subscription::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'current_plan_id' => $this->planA->id,
            'current_period_starts_at' => $cycleStart,
            'current_period_ends_at' => $cycleEnd,
        ]);

        // Active for 1 day (Sept 1 only)
        SubscriptionSegment::factory()->create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->planA->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => '2026-09-01 23:59:59',
            'snapshot_base_price' => 3000.00,
            'snapshot_included_usage_units' => 1000,
            'snapshot_overage_rate_per_unit' => 0.10,
        ]);

        $service = app(BillingService::class);
        $invoice = $service->generateInvoiceForSubscription($subscription, $cycleStart, $cycleEnd);

        // 1 day out of 30 = 3000 / 30 = 100.00
        $this->assertEquals(100.00, (float) $invoice->total);
    }

    /**
     * Test leap year February and different month lengths proration.
     */
    public function test_leap_year_february_proration(): void
    {
        // February 2028 is a leap year (29 days)
        $cycleStart = Carbon::parse('2028-02-01 00:00:00');
        $cycleEnd = Carbon::parse('2028-02-29 23:59:59');

        $subscription = Subscription::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'current_plan_id' => $this->planA->id,
            'current_period_starts_at' => $cycleStart,
            'current_period_ends_at' => $cycleEnd,
        ]);

        // Segment active for 14 days out of 29 days
        SubscriptionSegment::factory()->create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->planA->id,
            'starts_at' => '2028-02-01 00:00:00',
            'ends_at' => '2028-02-14 23:59:59',
            'snapshot_base_price' => 2900.00,
            'snapshot_included_usage_units' => 1000,
            'snapshot_overage_rate_per_unit' => 0.10,
        ]);

        $service = app(BillingService::class);
        $invoice = $service->generateInvoiceForSubscription($subscription, $cycleStart, $cycleEnd);

        // 2900 * 14 / 29 = 1400.00
        $this->assertEquals(1400.00, (float) $invoice->total);
    }

    /**
     * Test usage below included allowance produces zero overage.
     */
    public function test_usage_below_allowance_produces_zero_overage(): void
    {
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59');

        $subscription = Subscription::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'current_plan_id' => $this->planA->id,
            'current_period_starts_at' => $cycleStart,
            'current_period_ends_at' => $cycleEnd,
        ]);

        $segment = SubscriptionSegment::factory()->create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->planA->id,
            'starts_at' => $cycleStart,
            'ends_at' => null,
            'snapshot_base_price' => 1000.00,
            'snapshot_included_usage_units' => 500, // 500 included units
            'snapshot_overage_rate_per_unit' => 0.50,
        ]);

        // Daily usage = 300 units (below 500)
        DailyUsage::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'subscription_id' => $subscription->id,
            'subscription_segment_id' => $segment->id,
            'usage_date' => '2026-09-14',
            'total_usage_units' => 300,
        ]);

        $service = app(BillingService::class);
        $invoice = $service->generateInvoiceForSubscription($subscription, $cycleStart, $cycleEnd);

        $this->assertEquals(1000.00, (float) $invoice->total);
        $this->assertCount(1, $invoice->items);
        $this->assertEquals('base_fee', $invoice->items->first()->type);
    }

    /**
     * Test usage exactly at included allowance produces zero overage.
     */
    public function test_usage_at_allowance_produces_zero_overage(): void
    {
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59');

        $subscription = Subscription::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'current_plan_id' => $this->planA->id,
            'current_period_starts_at' => $cycleStart,
            'current_period_ends_at' => $cycleEnd,
        ]);

        $segment = SubscriptionSegment::factory()->create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->planA->id,
            'starts_at' => $cycleStart,
            'ends_at' => null,
            'snapshot_base_price' => 1000.00,
            'snapshot_included_usage_units' => 500,
            'snapshot_overage_rate_per_unit' => 0.50,
        ]);

        // Daily usage = 500 units (exactly equal)
        DailyUsage::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'subscription_id' => $subscription->id,
            'subscription_segment_id' => $segment->id,
            'usage_date' => '2026-09-14',
            'total_usage_units' => 500,
        ]);

        $service = app(BillingService::class);
        $invoice = $service->generateInvoiceForSubscription($subscription, $cycleStart, $cycleEnd);

        $this->assertEquals(1000.00, (float) $invoice->total);
        $this->assertCount(1, $invoice->items);
    }

    /**
     * Test usage above allowance calculates correct overage amount.
     */
    public function test_usage_above_allowance_calculates_correct_overage(): void
    {
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59');

        $subscription = Subscription::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'current_plan_id' => $this->planA->id,
            'current_period_starts_at' => $cycleStart,
            'current_period_ends_at' => $cycleEnd,
        ]);

        $segment = SubscriptionSegment::factory()->create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->planA->id,
            'starts_at' => $cycleStart,
            'ends_at' => null,
            'snapshot_base_price' => 1000.00,
            'snapshot_included_usage_units' => 1000,
            'snapshot_overage_rate_per_unit' => 0.10, // ₹0.10 per overage unit
        ]);

        // Usage = 1500 units (500 overage units)
        DailyUsage::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'subscription_id' => $subscription->id,
            'subscription_segment_id' => $segment->id,
            'usage_date' => '2026-09-14',
            'total_usage_units' => 1500,
        ]);

        $service = app(BillingService::class);
        $invoice = $service->generateInvoiceForSubscription($subscription, $cycleStart, $cycleEnd);

        // Base = 1000.00, Overage = 500 * 0.10 = 50.00 -> Total = 1050.00
        $this->assertEquals(1050.00, (float) $invoice->total);
        $this->assertCount(2, $invoice->items);

        $overageItem = $invoice->items->where('type', 'overage')->first();
        $this->assertNotNull($overageItem);
        $this->assertEquals(500, $overageItem->quantity);
        $this->assertEquals(50.00, (float) $overageItem->amount);
    }

    /**
     * Test mid-cycle plan upgrade bills pre-change usage at old rate and post-change at new rate.
     */
    public function test_mid_cycle_upgrade_bills_both_segments_correctly(): void
    {
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59'); // 30 days

        $subscription = Subscription::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'current_plan_id' => $this->planB->id,
            'current_period_starts_at' => $cycleStart,
            'current_period_ends_at' => $cycleEnd,
        ]);

        // Segment 1 (Plan A: Sept 1 to Sept 15 -> 15 days)
        // Base = 3000 (prorated: 1500), Included = 1000 (prorated: 500), Overage = 0.10
        $segment1 = SubscriptionSegment::factory()->create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->planA->id,
            'starts_at' => '2026-09-01 00:00:00',
            'ends_at' => '2026-09-15 23:59:59',
            'snapshot_base_price' => 3000.00,
            'snapshot_included_usage_units' => 1000,
            'snapshot_overage_rate_per_unit' => 0.10,
        ]);

        // Segment 2 (Plan B: Sept 16 to Sept 30 -> 15 days)
        // Base = 6000 (prorated: 3000), Included = 5000 (prorated: 2500), Overage = 0.05
        $segment2 = SubscriptionSegment::factory()->create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->planB->id,
            'starts_at' => '2026-09-16 00:00:00',
            'ends_at' => '2026-09-30 23:59:59',
            'snapshot_base_price' => 6000.00,
            'snapshot_included_usage_units' => 5000,
            'snapshot_overage_rate_per_unit' => 0.05,
        ]);

        // Usage for Segment 1: 800 units -> included = 500 -> overage = 300 units @ 0.10 = ₹30
        DailyUsage::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'subscription_id' => $subscription->id,
            'subscription_segment_id' => $segment1->id,
            'usage_date' => '2026-09-10',
            'total_usage_units' => 800,
        ]);

        // Usage for Segment 2: 3500 units -> included = 2500 -> overage = 1000 units @ 0.05 = ₹50
        DailyUsage::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'subscription_id' => $subscription->id,
            'subscription_segment_id' => $segment2->id,
            'usage_date' => '2026-09-20',
            'total_usage_units' => 3500,
        ]);

        $service = app(BillingService::class);
        $invoice = $service->generateInvoiceForSubscription($subscription, $cycleStart, $cycleEnd);

        // Expected Breakdown:
        // Segment 1: Base = 1500, Overage = 30 -> 1530.00
        // Segment 2: Base = 3000, Overage = 50 -> 3050.00
        // Total Invoice = 4580.00
        $this->assertEquals(4580.00, (float) $invoice->total);
        $this->assertCount(4, $invoice->items);

        $this->assertEquals(4580.00, (float) $invoice->subtotal);
        $this->assertEquals($invoice->total, $invoice->items->sum('amount'));
    }

    /**
     * Test historical pricing snapshot is respected even if plan pricing changes later.
     */
    public function test_historical_pricing_snapshot_is_respected(): void
    {
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59');

        $subscription = Subscription::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'current_plan_id' => $this->planA->id,
            'current_period_starts_at' => $cycleStart,
            'current_period_ends_at' => $cycleEnd,
        ]);

        // Snapshot created with old base price ₹2000
        SubscriptionSegment::factory()->create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->planA->id,
            'starts_at' => $cycleStart,
            'ends_at' => null,
            'snapshot_base_price' => 2000.00,
            'snapshot_included_usage_units' => 1000,
            'snapshot_overage_rate_per_unit' => 0.10,
        ]);

        // Plan A pricing is edited later to ₹5000
        $this->planA->update(['base_price' => 5000.00]);

        $service = app(BillingService::class);
        $invoice = $service->generateInvoiceForSubscription($subscription, $cycleStart, $cycleEnd);

        // Billing must use snapshot base price ₹2000.00, NOT updated plan base price ₹5000.00
        $this->assertEquals(2000.00, (float) $invoice->total);
    }

    /**
     * Test retrying billing for the same cycle returns existing invoice without duplicates.
     */
    public function test_retrying_billing_does_not_create_duplicate_invoices(): void
    {
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59');

        $subscription = Subscription::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'current_plan_id' => $this->planA->id,
            'current_period_starts_at' => $cycleStart,
            'current_period_ends_at' => $cycleEnd,
        ]);

        SubscriptionSegment::factory()->create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->planA->id,
            'starts_at' => $cycleStart,
            'ends_at' => null,
            'snapshot_base_price' => 3000.00,
            'snapshot_included_usage_units' => 1000,
            'snapshot_overage_rate_per_unit' => 0.10,
        ]);

        $service = app(BillingService::class);

        // First attempt -> Invoice created
        $invoice1 = $service->generateInvoiceForSubscription($subscription, $cycleStart, $cycleEnd);

        // Second attempt -> Returns identical invoice
        $invoice2 = $service->generateInvoiceForSubscription($subscription, $cycleStart, $cycleEnd);

        $this->assertEquals($invoice1->id, $invoice2->id);
        $this->assertEquals(1, Invoice::count());
    }

    /**
     * Test invoice total equals invoice item totals sum.
     */
    public function test_invoice_total_equals_invoice_item_totals(): void
    {
        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59');

        $subscription = Subscription::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'current_plan_id' => $this->planA->id,
            'current_period_starts_at' => $cycleStart,
            'current_period_ends_at' => $cycleEnd,
        ]);

        $segment = SubscriptionSegment::factory()->create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->planA->id,
            'starts_at' => $cycleStart,
            'ends_at' => null,
            'snapshot_base_price' => 3000.00,
            'snapshot_included_usage_units' => 100,
            'snapshot_overage_rate_per_unit' => 0.3333,
        ]);

        DailyUsage::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'subscription_id' => $subscription->id,
            'subscription_segment_id' => $segment->id,
            'usage_date' => '2026-09-14',
            'total_usage_units' => 1100, // 1000 overage units @ 0.3333 = ₹333.30
        ]);

        $service = app(BillingService::class);
        $invoice = $service->generateInvoiceForSubscription($subscription, $cycleStart, $cycleEnd);

        $itemsSum = (float) $invoice->items->sum('amount');
        $this->assertEquals((float) $invoice->total, $itemsSum);
        $this->assertEquals((float) $invoice->subtotal, $itemsSum);
    }

    /**
     * Test billing command queues job or executes billing synchronously.
     */
    public function test_billing_command_queues_or_executes_billing(): void
    {
        Queue::fake();

        $cycleStart = Carbon::parse('2026-09-01 00:00:00');
        $cycleEnd = Carbon::parse('2026-09-30 23:59:59');

        $subscription = Subscription::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'current_plan_id' => $this->planA->id,
            'current_period_starts_at' => $cycleStart,
            'current_period_ends_at' => $cycleEnd,
            'status' => 'active',
        ]);

        SubscriptionSegment::factory()->create([
            'subscription_id' => $subscription->id,
            'plan_id' => $this->planA->id,
            'starts_at' => $cycleStart,
            'ends_at' => null,
        ]);

        // Command with --queue flag
        $this->artisan('billing:generate', [
            '--date' => '2026-09-30',
            '--queue' => true,
        ])->assertExitCode(0);

        Queue::assertPushed(ProcessSubscriptionBilling::class, function ($job) use ($subscription) {
            return $job->subscriptionId === $subscription->id;
        });

        // Command synchronous execution
        $this->artisan('billing:generate', [
            '--subscription' => $subscription->id,
        ])->assertExitCode(0);

        $this->assertEquals(1, Invoice::count());
    }
}
