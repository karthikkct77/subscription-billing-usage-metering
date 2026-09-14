<?php

namespace App\Services;

use App\Models\DailyUsage;
use App\Models\UsageEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UsageAggregationService
{
    /**
     * Aggregate usage events into daily_usage records.
     *
     * @param  int|null  $merchantId  Optional merchant filter
     * @param  int|null  $customerId  Optional customer filter
     * @param  string|null  $fromDate  Optional start date filter (inclusive)
     * @param  string|null  $toDate  Optional end date filter (inclusive)
     * @param  int  $chunkSize  Chunk size for primary-key iteration
     * @return int Number of daily usage records updated/created
     */
    public function aggregate(
        ?int $merchantId = null,
        ?int $customerId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        int $chunkSize = 1000
    ): int {
        $query = UsageEvent::query();

        if ($merchantId !== null) {
            $query->where('merchant_id', $merchantId);
        }

        if ($customerId !== null) {
            $query->where('customer_id', $customerId);
        }

        if ($fromDate !== null) {
            $query->where('occurred_at', '>=', Carbon::parse($fromDate)->startOfDay());
        }

        if ($toDate !== null) {
            $query->where('occurred_at', '<=', Carbon::parse($toDate)->endOfDay());
        }

        $recordsProcessed = 0;
        $processedCombinations = [];

        // Primary-key based chunking using lazyById to avoid O(N^2) OFFSET performance degradation
        $query->lazyById($chunkSize)->chunk($chunkSize)->each(function ($chunk) use (&$recordsProcessed, &$processedCombinations, $fromDate, $toDate) {
            $combinationsInChunk = [];

            foreach ($chunk as $event) {
                $usageDate = Carbon::parse($event->occurred_at)->toDateString();
                $key = sprintf(
                    '%d_%d_%d_%s_%s',
                    $event->merchant_id,
                    $event->customer_id,
                    $event->subscription_id,
                    $event->subscription_segment_id ?? 'null',
                    $usageDate
                );

                if (! isset($processedCombinations[$key])) {
                    $combinationsInChunk[$key] = [
                        'merchant_id' => $event->merchant_id,
                        'customer_id' => $event->customer_id,
                        'subscription_id' => $event->subscription_id,
                        'subscription_segment_id' => $event->subscription_segment_id,
                        'usage_date' => $usageDate,
                    ];
                    $processedCombinations[$key] = true;
                }
            }

            if (empty($combinationsInChunk)) {
                return;
            }

            // Recompute exact total_usage_units for each unique key to ensure idempotency and handle late events
            DB::transaction(function () use ($combinationsInChunk, &$recordsProcessed, $fromDate, $toDate) {
                foreach ($combinationsInChunk as $combo) {
                    $sumQuery = UsageEvent::where('merchant_id', $combo['merchant_id'])
                        ->where('customer_id', $combo['customer_id'])
                        ->where('subscription_id', $combo['subscription_id'])
                        ->whereDate('occurred_at', $combo['usage_date']);

                    if ($combo['subscription_segment_id'] !== null) {
                        $sumQuery->where('subscription_segment_id', $combo['subscription_segment_id']);
                    } else {
                        $sumQuery->whereNull('subscription_segment_id');
                    }

                    // Enforce date window bounds if provided
                    if ($fromDate !== null) {
                        $sumQuery->where('occurred_at', '>=', Carbon::parse($fromDate)->startOfDay());
                    }
                    if ($toDate !== null) {
                        $sumQuery->where('occurred_at', '<=', Carbon::parse($toDate)->endOfDay());
                    }

                    $totalUnits = (int) $sumQuery->sum('usage_units');

                    DailyUsage::updateOrCreate(
                        [
                            'subscription_id' => $combo['subscription_id'],
                            'subscription_segment_id' => $combo['subscription_segment_id'],
                            'usage_date' => $combo['usage_date'],
                        ],
                        [
                            'merchant_id' => $combo['merchant_id'],
                            'customer_id' => $combo['customer_id'],
                            'total_usage_units' => $totalUnits,
                        ]
                    );

                    $recordsProcessed++;
                }
            });
        });

        Log::info('Daily usage aggregation completed', [
            'merchant_id' => $merchantId,
            'customer_id' => $customerId,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'daily_records_updated' => $recordsProcessed,
        ]);

        return $recordsProcessed;
    }
}
