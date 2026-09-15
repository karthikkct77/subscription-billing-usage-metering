<?php

namespace Tests\Feature;

use App\Jobs\AggregateDailyUsage;
use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use App\Models\UsageEvent;
use App\Services\UsageAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UsageAggregationTest extends TestCase
{
    use RefreshDatabase;

    protected Merchant $merchant;

    protected Customer $customer1;

    protected Customer $customer2;

    protected Subscription $subscription1;

    protected Subscription $subscription2;

    protected SubscriptionSegment $segment1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = Merchant::factory()->create();

        $this->customer1 = Customer::factory()->create(['merchant_id' => $this->merchant->id]);
        $this->subscription1 = Subscription::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer1->id,
            'status' => 'active',
        ]);
        $this->segment1 = SubscriptionSegment::factory()->create([
            'subscription_id' => $this->subscription1->id,
        ]);

        $this->customer2 = Customer::factory()->create(['merchant_id' => $this->merchant->id]);
        $this->subscription2 = Subscription::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer2->id,
            'status' => 'active',
        ]);
    }

    /**
     * Test aggregating multiple events for the same customer and day creates a single daily_usage record.
     */
    public function test_aggregates_multiple_events_for_same_customer_and_day(): void
    {
        $date = '2026-09-14';

        UsageEvent::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer1->id,
            'subscription_id' => $this->subscription1->id,
            'subscription_segment_id' => $this->segment1->id,
            'usage_units' => 10,
            'occurred_at' => "{$date} 08:00:00",
        ]);

        UsageEvent::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer1->id,
            'subscription_id' => $this->subscription1->id,
            'subscription_segment_id' => $this->segment1->id,
            'usage_units' => 25,
            'occurred_at' => "{$date} 14:30:00",
        ]);

        UsageEvent::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer1->id,
            'subscription_id' => $this->subscription1->id,
            'subscription_segment_id' => $this->segment1->id,
            'usage_units' => 15,
            'occurred_at' => "{$date} 22:15:00",
        ]);

        $service = app(UsageAggregationService::class);
        $service->aggregate();

        $this->assertDatabaseHas('daily_usages', [
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer1->id,
            'subscription_id' => $this->subscription1->id,
            'subscription_segment_id' => $this->segment1->id,
            'usage_date' => $date,
            'total_usage_units' => 50,
        ]);

        $this->assertEquals(1, DailyUsage::count());
    }

    /**
     * Test running aggregation twice is idempotent and does not double-count.
     */
    public function test_running_aggregation_twice_is_idempotent(): void
    {
        $date = '2026-09-14';

        UsageEvent::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer1->id,
            'subscription_id' => $this->subscription1->id,
            'subscription_segment_id' => $this->segment1->id,
            'usage_units' => 40,
            'occurred_at' => "{$date} 10:00:00",
        ]);

        $service = app(UsageAggregationService::class);

        // First run
        $service->aggregate();
        $this->assertEquals(40, DailyUsage::first()->total_usage_units);

        // Second run
        $service->aggregate();

        // Count and total units must remain unchanged
        $this->assertEquals(1, DailyUsage::count());
        $this->assertEquals(40, DailyUsage::first()->total_usage_units);
    }

    /**
     * Test multiple customers are aggregated independently.
     */
    public function test_multiple_customers_are_aggregated_independently(): void
    {
        $date = '2026-09-14';

        UsageEvent::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer1->id,
            'subscription_id' => $this->subscription1->id,
            'usage_units' => 100,
            'occurred_at' => "{$date} 10:00:00",
        ]);

        UsageEvent::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer2->id,
            'subscription_id' => $this->subscription2->id,
            'usage_units' => 250,
            'occurred_at' => "{$date} 11:00:00",
        ]);

        $service = app(UsageAggregationService::class);
        $service->aggregate();

        $this->assertDatabaseHas('daily_usages', [
            'customer_id' => $this->customer1->id,
            'total_usage_units' => 100,
        ]);

        $this->assertDatabaseHas('daily_usages', [
            'customer_id' => $this->customer2->id,
            'total_usage_units' => 250,
        ]);
    }

    /**
     * Test different dates are aggregated independently.
     */
    public function test_different_dates_are_aggregated_independently(): void
    {
        UsageEvent::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer1->id,
            'subscription_id' => $this->subscription1->id,
            'usage_units' => 15,
            'occurred_at' => '2026-09-14 10:00:00',
        ]);

        UsageEvent::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer1->id,
            'subscription_id' => $this->subscription1->id,
            'usage_units' => 35,
            'occurred_at' => '2026-09-15 10:00:00',
        ]);

        $service = app(UsageAggregationService::class);
        $service->aggregate();

        $this->assertDatabaseHas('daily_usages', [
            'customer_id' => $this->customer1->id,
            'usage_date' => '2026-09-14',
            'total_usage_units' => 15,
        ]);

        $this->assertDatabaseHas('daily_usages', [
            'customer_id' => $this->customer1->id,
            'usage_date' => '2026-09-15',
            'total_usage_units' => 35,
        ]);
    }

    /**
     * Test date range filtering in aggregation service.
     */
    public function test_date_range_filtering_works_correctly(): void
    {
        UsageEvent::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer1->id,
            'subscription_id' => $this->subscription1->id,
            'usage_units' => 10,
            'occurred_at' => '2026-09-01 10:00:00',
        ]);

        UsageEvent::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer1->id,
            'subscription_id' => $this->subscription1->id,
            'usage_units' => 50,
            'occurred_at' => '2026-09-14 10:00:00',
        ]);

        $service = app(UsageAggregationService::class);
        // Aggregate only Sept 14
        $service->aggregate(fromDate: '2026-09-14', toDate: '2026-09-14');

        $this->assertEquals(1, DailyUsage::count());
        $this->assertDatabaseHas('daily_usages', [
            'usage_date' => '2026-09-14',
            'total_usage_units' => 50,
        ]);
        $this->assertDatabaseMissing('daily_usages', [
            'usage_date' => '2026-09-01',
        ]);
    }

    /**
     * Test late-arriving events correct historical daily usage totals.
     */
    public function test_late_arriving_events_correct_historical_daily_usage_totals(): void
    {
        $pastDate = '2026-09-10';

        // Initial event on Sept 10
        UsageEvent::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer1->id,
            'subscription_id' => $this->subscription1->id,
            'usage_units' => 100,
            'occurred_at' => "{$pastDate} 12:00:00",
        ]);

        $service = app(UsageAggregationService::class);
        $service->aggregate();

        $this->assertDatabaseHas('daily_usages', [
            'usage_date' => $pastDate,
            'total_usage_units' => 100,
        ]);

        // Late event for Sept 10 arrives later (e.g. on Sept 14)
        UsageEvent::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer1->id,
            'subscription_id' => $this->subscription1->id,
            'usage_units' => 50, // Late event
            'occurred_at' => "{$pastDate} 18:30:00",
        ]);

        // Re-run aggregation
        $service->aggregate();

        // Historical daily total is updated from 100 to 150
        $this->assertDatabaseHas('daily_usages', [
            'usage_date' => $pastDate,
            'total_usage_units' => 150,
        ]);
    }

    /**
     * Test mid-cycle subscription plan changes preserve segment isolation in daily usage.
     */
    public function test_mid_cycle_plan_changes_preserve_segment_isolation(): void
    {
        $segment2 = SubscriptionSegment::factory()->create([
            'subscription_id' => $this->subscription1->id,
        ]);

        $date = '2026-09-14';

        // Event under Segment 1
        UsageEvent::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer1->id,
            'subscription_id' => $this->subscription1->id,
            'subscription_segment_id' => $this->segment1->id,
            'usage_units' => 30,
            'occurred_at' => "{$date} 09:00:00",
        ]);

        // Event under Segment 2 on same day
        UsageEvent::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer1->id,
            'subscription_id' => $this->subscription1->id,
            'subscription_segment_id' => $segment2->id,
            'usage_units' => 70,
            'occurred_at' => "{$date} 15:00:00",
        ]);

        $service = app(UsageAggregationService::class);
        $service->aggregate();

        $this->assertEquals(2, DailyUsage::count());

        $this->assertDatabaseHas('daily_usages', [
            'subscription_segment_id' => $this->segment1->id,
            'usage_date' => $date,
            'total_usage_units' => 30,
        ]);

        $this->assertDatabaseHas('daily_usages', [
            'subscription_segment_id' => $segment2->id,
            'usage_date' => $date,
            'total_usage_units' => 70,
        ]);
    }

    /**
     * Test Artisan command dispatches job to queue.
     */
    public function test_artisan_command_queues_job(): void
    {
        Queue::fake();

        $this->artisan('usage:aggregate', [
            '--merchant' => $this->merchant->id,
            '--from' => '2026-09-01',
            '--to' => '2026-09-14',
            '--queue' => true,
        ])->assertExitCode(0);

        Queue::assertPushed(AggregateDailyUsage::class, function ($job) {
            return $job->merchantId === $this->merchant->id
                && $job->fromDate === '2026-09-01'
                && $job->toDate === '2026-09-14';
        });
    }

    /**
     * Test Artisan command executes synchronously when --queue is omitted.
     */
    public function test_artisan_command_executes_synchronously_by_default(): void
    {
        UsageEvent::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer1->id,
            'subscription_id' => $this->subscription1->id,
            'usage_units' => 88,
            'occurred_at' => '2026-09-14 12:00:00',
        ]);

        $this->artisan('usage:aggregate')
            ->assertExitCode(0);

        $this->assertDatabaseHas('daily_usages', [
            'customer_id' => $this->customer1->id,
            'total_usage_units' => 88,
        ]);
    }

    /**
     * Test chunked processing using small chunk sizes handles multiple primary-key iterations correctly.
     */
    public function test_chunked_processing_handles_multiple_chunks_correctly(): void
    {
        $date = '2026-09-14';

        // Create 25 events (10 units each = 250 units total)
        for ($i = 0; $i < 25; $i++) {
            UsageEvent::factory()->create([
                'merchant_id' => $this->merchant->id,
                'customer_id' => $this->customer1->id,
                'subscription_id' => $this->subscription1->id,
                'subscription_segment_id' => $this->segment1->id,
                'usage_units' => 10,
                'occurred_at' => "{$date} 10:00:00",
            ]);
        }

        $service = app(UsageAggregationService::class);
        // Process with chunk size of 10 (spans 3 chunks)
        $service->aggregate(chunkSize: 10);

        $this->assertDatabaseHas('daily_usages', [
            'customer_id' => $this->customer1->id,
            'usage_date' => $date,
            'total_usage_units' => 250,
        ]);
    }
}
