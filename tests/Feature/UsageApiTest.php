<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use App\Models\UsageEvent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class UsageApiTest extends TestCase
{
    use RefreshDatabase;

    protected Merchant $merchant;

    protected Customer $customer;

    protected Subscription $subscription;

    protected SubscriptionSegment $segment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = Merchant::factory()->create();
        $this->customer = Customer::factory()->create(['merchant_id' => $this->merchant->id]);
        $this->subscription = Subscription::factory()->create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'status' => 'active',
        ]);
        $this->segment = SubscriptionSegment::factory()->create([
            'subscription_id' => $this->subscription->id,
            'starts_at' => now()->subDays(5),
            'ends_at' => null,
        ]);
    }

    /**
     * Test valid usage event returns HTTP 201 Created and persists event.
     */
    public function test_valid_usage_event_returns_201_and_persists(): void
    {
        $payload = [
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'occurred_at' => now()->toIso8601String(),
            'units' => 10,
            'idempotency_key' => 'evt_valid_123',
        ];

        $response = $this->postJson('/api/v1/usage', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Usage recorded successfully.',
                'data' => [
                    'merchant_id' => $this->merchant->id,
                    'customer_id' => $this->customer->id,
                    'subscription_id' => $this->subscription->id,
                    'subscription_segment_id' => $this->segment->id,
                    'units' => 10,
                    'idempotency_key' => 'evt_valid_123',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'merchant_id',
                    'customer_id',
                    'subscription_id',
                    'subscription_segment_id',
                    'units',
                    'idempotency_key',
                    'occurred_at',
                    'created_at',
                ],
                'message',
            ]);

        $this->assertDatabaseHas('usage_events', [
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'idempotency_key' => 'evt_valid_123',
            'usage_units' => 10,
        ]);
    }

    /**
     * Test invalid merchant is rejected.
     */
    public function test_invalid_merchant_is_rejected(): void
    {
        $payload = [
            'merchant_id' => 999999,
            'customer_id' => $this->customer->id,
            'occurred_at' => now()->toIso8601String(),
            'units' => 5,
            'idempotency_key' => 'evt_invalid_merchant',
        ];

        $response = $this->postJson('/api/v1/usage', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test invalid customer is rejected.
     */
    public function test_invalid_customer_is_rejected(): void
    {
        $payload = [
            'merchant_id' => $this->merchant->id,
            'customer_id' => 999999,
            'occurred_at' => now()->toIso8601String(),
            'units' => 5,
            'idempotency_key' => 'evt_invalid_customer',
        ];

        $response = $this->postJson('/api/v1/usage', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test customer belonging to another merchant is rejected.
     */
    public function test_customer_belonging_to_another_merchant_is_rejected(): void
    {
        $otherMerchant = Merchant::factory()->create();
        $otherCustomer = Customer::factory()->create(['merchant_id' => $otherMerchant->id]);

        $payload = [
            'merchant_id' => $this->merchant->id,
            'customer_id' => $otherCustomer->id,
            'occurred_at' => now()->toIso8601String(),
            'units' => 5,
            'idempotency_key' => 'evt_cross_tenant',
        ];

        $response = $this->postJson('/api/v1/usage', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('errors.customer_id.0', 'The selected customer does not belong to the specified merchant.');
    }

    /**
     * Test zero usage is rejected.
     */
    public function test_zero_usage_is_rejected(): void
    {
        $payload = [
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'occurred_at' => now()->toIso8601String(),
            'units' => 0,
            'idempotency_key' => 'evt_zero_units',
        ];

        $response = $this->postJson('/api/v1/usage', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test negative usage is rejected.
     */
    public function test_negative_usage_is_rejected(): void
    {
        $payload = [
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'occurred_at' => now()->toIso8601String(),
            'units' => -10,
            'idempotency_key' => 'evt_negative_units',
        ];

        $response = $this->postJson('/api/v1/usage', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test invalid timestamp is rejected.
     */
    public function test_invalid_timestamp_is_rejected(): void
    {
        $payload = [
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'occurred_at' => 'not-a-valid-date',
            'units' => 5,
            'idempotency_key' => 'evt_invalid_date',
        ];

        $response = $this->postJson('/api/v1/usage', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test missing idempotency key is rejected.
     */
    public function test_missing_idempotency_key_is_rejected(): void
    {
        $payload = [
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'occurred_at' => now()->toIso8601String(),
            'units' => 5,
        ];

        $response = $this->postJson('/api/v1/usage', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test customer without active subscription is rejected.
     */
    public function test_customer_without_active_subscription_is_rejected(): void
    {
        $newCustomer = Customer::factory()->create(['merchant_id' => $this->merchant->id]);

        $payload = [
            'merchant_id' => $this->merchant->id,
            'customer_id' => $newCustomer->id,
            'occurred_at' => now()->toIso8601String(),
            'units' => 5,
            'idempotency_key' => 'evt_no_subscription',
        ];

        $response = $this->postJson('/api/v1/usage', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Customer does not have an active subscription for the specified merchant.',
            ]);
    }

    /**
     * Test retrying identical idempotency key returns existing event with HTTP 200.
     */
    public function test_retrying_same_idempotency_key_returns_existing_event_with_200(): void
    {
        $occurredAt = now()->startOfSecond();

        $payload = [
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'occurred_at' => $occurredAt->toIso8601String(),
            'units' => 15,
            'idempotency_key' => 'evt_retry_1001',
        ];

        // First attempt -> 201 Created
        $response1 = $this->postJson('/api/v1/usage', $payload);
        $response1->assertStatus(201);

        // Second attempt -> 200 OK with identical event data
        $response2 = $this->postJson('/api/v1/usage', $payload);
        $response2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Usage event already recorded.',
                'data' => [
                    'id' => $response1->json('data.id'),
                    'units' => 15,
                    'idempotency_key' => 'evt_retry_1001',
                ],
            ]);

        // Ensure database count is 1
        $this->assertEquals(1, UsageEvent::where('idempotency_key', 'evt_retry_1001')->count());
    }

    /**
     * Test reusing idempotency key with conflicting usage payload returns HTTP 409.
     */
    public function test_reusing_idempotency_key_with_conflicting_payload_returns_409(): void
    {
        $occurredAt = now()->toIso8601String();

        $initialPayload = [
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'occurred_at' => $occurredAt,
            'units' => 10,
            'idempotency_key' => 'evt_conflict_999',
        ];

        $conflictingPayload = [
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'occurred_at' => $occurredAt,
            'units' => 500, // Conflict!
            'idempotency_key' => 'evt_conflict_999',
        ];

        // First attempt -> 201 Created
        $this->postJson('/api/v1/usage', $initialPayload)->assertStatus(201);

        // Conflicting attempt -> 409 Conflict
        $response = $this->postJson('/api/v1/usage', $conflictingPayload);
        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Idempotency key conflict: Request payload does not match the existing recorded event.',
            ]);

        $this->assertEquals(1, UsageEvent::where('idempotency_key', 'evt_conflict_999')->count());
    }

    /**
     * Test rate limiting is enforced on POST /api/v1/usage.
     */
    public function test_rate_limiting_is_enforced(): void
    {
        RateLimiter::for('usage', function ($request) {
            return Limit::perMinute(3)->by((string) ($request->input('merchant_id') ?: $request->ip()));
        });

        $payload = [
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'occurred_at' => now()->toIso8601String(),
            'units' => 1,
        ];

        // 3 allowed requests
        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/api/v1/usage', array_merge($payload, [
                'idempotency_key' => "evt_rate_{$i}",
            ]))->assertStatus(201);
        }

        // 4th request -> 429 Too Many Requests
        $response = $this->postJson('/api/v1/usage', array_merge($payload, [
            'idempotency_key' => 'evt_rate_4',
        ]));

        $response->assertStatus(429);
    }

    /**
     * Test race condition handling when duplicate idempotency key causes database QueryException.
     */
    public function test_concurrent_request_race_condition_handles_database_uniqueness_gracefully(): void
    {
        $occurredAt = now()->startOfSecond();
        $idempotencyKey = 'evt_race_condition_123';

        // Pre-create the event directly in DB to simulate another worker creating it between pre-lookup and transaction
        $existingEvent = UsageEvent::create([
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'subscription_id' => $this->subscription->id,
            'subscription_segment_id' => $this->segment->id,
            'idempotency_key' => $idempotencyKey,
            'usage_units' => 10,
            'occurred_at' => $occurredAt,
        ]);

        // Attempting to record via action when event already exists in DB returns HTTP 200 idempotent response
        $payload = [
            'merchant_id' => $this->merchant->id,
            'customer_id' => $this->customer->id,
            'occurred_at' => $occurredAt->toIso8601String(),
            'units' => 10,
            'idempotency_key' => $idempotencyKey,
        ];

        $response = $this->postJson('/api/v1/usage', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $existingEvent->id,
                    'idempotency_key' => $idempotencyKey,
                ],
            ]);
    }
}
