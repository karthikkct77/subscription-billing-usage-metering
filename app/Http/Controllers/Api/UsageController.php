<?php

namespace App\Http\Controllers\Api;

use App\Actions\RecordUsageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordUsageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class UsageController extends Controller
{
    /**
     * Record a new usage event.
     */
    public function store(RecordUsageRequest $request, RecordUsageAction $action): JsonResponse
    {
        $result = $action->execute($request->validated());

        return match ($result['status']) {
            'created' => $this->successResponse(
                data: $this->formatEventResource($result['event']),
                message: 'Usage recorded successfully.',
                statusCode: 201
            ),
            'existing' => $this->successResponse(
                data: $this->formatEventResource($result['event']),
                message: 'Usage event already recorded.',
                statusCode: 200
            ),
            'conflict' => $this->errorResponse(
                message: $result['message'],
                statusCode: 409
            ),
            'no_subscription' => $this->errorResponse(
                message: $result['message'],
                statusCode: 422
            ),
            default => $this->errorResponse(
                message: 'Failed to record usage event.',
                statusCode: 500
            ),
        };
    }

    /**
     * Format usage event model for JSON response.
     */
    protected function formatEventResource($event): array
    {
        return [
            'id' => $event->id,
            'merchant_id' => $event->merchant_id,
            'customer_id' => $event->customer_id,
            'subscription_id' => $event->subscription_id,
            'subscription_segment_id' => $event->subscription_segment_id,
            'units' => (int) $event->usage_units,
            'idempotency_key' => $event->idempotency_key,
            'occurred_at' => Carbon::parse($event->occurred_at)->toIso8601String(),
            'created_at' => Carbon::parse($event->created_at)->toIso8601String(),
        ];
    }
}
