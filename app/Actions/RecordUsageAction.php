<?php

namespace App\Actions;

use App\Models\Subscription;
use App\Models\UsageEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecordUsageAction
{
    /**
     * Execute the usage recording action.
     */
    public function execute(array $data): array
    {
        $merchantId = (int) $data['merchant_id'];
        $customerId = (int) $data['customer_id'];
        $idempotencyKey = (string) $data['idempotency_key'];
        $units = (int) $data['units'];
        $occurredAt = Carbon::parse($data['occurred_at']);

        // 1. Verify active subscription for customer
        $subscription = Subscription::where('merchant_id', $merchantId)
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->first();

        if (! $subscription) {
            return [
                'status' => 'no_subscription',
                'message' => 'Customer does not have an active subscription for the specified merchant.',
            ];
        }

        // 2. Resolve active subscription segment for the event timestamp
        $segment = $subscription->segments()
            ->where('starts_at', '<=', $occurredAt)
            ->where(function ($query) use ($occurredAt) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $occurredAt);
            })
            ->first();

        // 3. Pre-insert idempotency lookup
        $existingEvent = UsageEvent::where('merchant_id', $merchantId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingEvent) {
            return $this->evaluateIdempotencyMatch($existingEvent, $customerId, $units, $occurredAt);
        }

        // 4. Atomic Database Transaction with Race Condition Protection
        try {
            $event = DB::transaction(function () use ($merchantId, $customerId, $subscription, $segment, $idempotencyKey, $units, $occurredAt) {
                return UsageEvent::create([
                    'merchant_id' => $merchantId,
                    'customer_id' => $customerId,
                    'subscription_id' => $subscription->id,
                    'subscription_segment_id' => $segment?->id,
                    'idempotency_key' => $idempotencyKey,
                    'usage_units' => $units,
                    'occurred_at' => $occurredAt,
                ]);
            });

            return [
                'status' => 'created',
                'event' => $event,
            ];
        } catch (QueryException $e) {
            // Handle race condition where a concurrent request inserted the key first
            Log::warning('Duplicate idempotency key detected during concurrent insert', [
                'merchant_id' => $merchantId,
                'idempotency_key' => $idempotencyKey,
                'error' => $e->getMessage(),
            ]);

            $concurrentEvent = UsageEvent::where('merchant_id', $merchantId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($concurrentEvent) {
                return $this->evaluateIdempotencyMatch($concurrentEvent, $customerId, $units, $occurredAt);
            }

            throw $e;
        }
    }

    /**
     * Compare an existing event payload against incoming request data to detect conflicts.
     */
    protected function evaluateIdempotencyMatch(UsageEvent $event, int $customerId, int $units, Carbon $occurredAt): array
    {
        $sameCustomer = (int) $event->customer_id === $customerId;
        $sameUnits = (int) $event->usage_units === $units;
        $sameTimestamp = Carbon::parse($event->occurred_at)->equalTo($occurredAt);

        if ($sameCustomer && $sameUnits && $sameTimestamp) {
            return [
                'status' => 'existing',
                'event' => $event,
            ];
        }

        return [
            'status' => 'conflict',
            'message' => 'Idempotency key conflict: Request payload does not match the existing recorded event.',
        ];
    }
}
