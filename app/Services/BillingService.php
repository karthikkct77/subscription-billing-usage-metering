<?php

namespace App\Services;

use App\Models\DailyUsage;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingService
{
    /**
     * Generate an invoice for a subscription billing cycle.
     */
    public function generateInvoiceForSubscription(
        Subscription $subscription,
        ?Carbon $periodStartsAt = null,
        ?Carbon $periodEndsAt = null
    ): Invoice {
        $cycleStart = $periodStartsAt
            ? Carbon::parse($periodStartsAt)->startOfDay()
            : Carbon::parse($subscription->current_period_starts_at)->startOfDay();

        $cycleEnd = $periodEndsAt
            ? Carbon::parse($periodEndsAt)->endOfDay()
            : Carbon::parse($subscription->current_period_ends_at)->endOfDay();

        // 1. Pre-check idempotency: Return existing invoice if already billed for this period
        $existingInvoice = Invoice::where('subscription_id', $subscription->id)
            ->where('period_starts_at', $cycleStart)
            ->where('period_ends_at', $cycleEnd)
            ->first();

        if ($existingInvoice) {
            return $existingInvoice;
        }

        // 2. Calculate total calendar days in full billing cycle
        $totalCycleDays = $this->calculateCycleDays($cycleStart, $cycleEnd);

        // 3. Retrieve historical subscription segments overlapping this cycle
        $segments = $subscription->segments()
            ->where('starts_at', '<=', $cycleEnd)
            ->where(function ($query) use ($cycleStart) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $cycleStart);
            })
            ->orderBy('starts_at', 'asc')
            ->get();

        $invoiceItemsData = [];

        foreach ($segments as $segment) {
            $segmentItems = $this->calculateSegmentBillingData($subscription, $segment, $cycleStart, $cycleEnd, $totalCycleDays);
            foreach ($segmentItems as $item) {
                $invoiceItemsData[] = $item;
            }
        }

        $subtotal = round(array_sum(array_column($invoiceItemsData, 'amount')), 2);
        $total = $subtotal;
        $invoiceNumber = sprintf('INV-%d-%s', $subscription->id, $cycleStart->format('YmdHis'));

        // 4. Atomic database transaction with race condition handling
        try {
            return DB::transaction(function () use ($subscription, $cycleStart, $cycleEnd, $invoiceNumber, $subtotal, $total, $invoiceItemsData) {
                $invoice = Invoice::create([
                    'merchant_id' => $subscription->merchant_id,
                    'customer_id' => $subscription->customer_id,
                    'subscription_id' => $subscription->id,
                    'invoice_number' => $invoiceNumber,
                    'period_starts_at' => $cycleStart,
                    'period_ends_at' => $cycleEnd,
                    'status' => 'draft',
                    'subtotal' => $subtotal,
                    'total' => $total,
                    'due_at' => $cycleEnd->copy()->addDays(14),
                ]);

                foreach ($invoiceItemsData as $itemData) {
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'subscription_segment_id' => $itemData['subscription_segment_id'],
                        'type' => $itemData['type'],
                        'description' => $itemData['description'],
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'amount' => $itemData['amount'],
                    ]);
                }

                Log::info('Generated invoice for subscription', [
                    'invoice_id' => $invoice->id,
                    'subscription_id' => $subscription->id,
                    'total' => $total,
                ]);

                return $invoice->load('items');
            });
        } catch (QueryException $e) {
            // Handle concurrent attempt race condition
            $concurrentInvoice = Invoice::where('subscription_id', $subscription->id)
                ->where('period_starts_at', $cycleStart)
                ->where('period_ends_at', $cycleEnd)
                ->first();

            if ($concurrentInvoice) {
                return $concurrentInvoice->load('items');
            }

            throw $e;
        }
    }

    /**
     * Calculate total days in a billing cycle.
     */
    public function calculateCycleDays(Carbon $cycleStart, Carbon $cycleEnd): int
    {
        $seconds = $cycleStart->diffInSeconds($cycleEnd->copy()->addSecond());

        return (int) max(1, (int) round($seconds / 86400));
    }

    /**
     * Calculate active days of a segment within the billing cycle.
     */
    public function calculateSegmentActiveDays(SubscriptionSegment $segment, Carbon $cycleStart, Carbon $cycleEnd): array
    {
        $startsAt = Carbon::parse($segment->starts_at);
        $endsAt = $segment->ends_at ? Carbon::parse($segment->ends_at) : null;

        $effectiveStart = $startsAt->greaterThan($cycleStart) ? $startsAt : $cycleStart;
        $effectiveEnd = ($endsAt !== null && $endsAt->lessThan($cycleEnd)) ? $endsAt : $cycleEnd;

        // If segment boundaries are exact days (00:00:00 to 23:59:59), normalize end to endOfDay
        if ($effectiveEnd->format('H:i:s') === '23:59:59') {
            $effectiveEnd = $effectiveEnd->copy()->endOfDay();
        }

        $seconds = $effectiveStart->diffInSeconds($effectiveEnd->copy()->addSecond());
        $activeDays = (int) max(1, (int) round($seconds / 86400));

        return [
            'start' => $effectiveStart,
            'end' => $effectiveEnd,
            'active_days' => $activeDays,
        ];
    }

    /**
     * Calculate prorated base charge for a segment.
     */
    public function calculateProratedBaseCharge(float $snapshotBasePrice, int $activeDays, int $totalCycleDays): float
    {
        if ($activeDays >= $totalCycleDays) {
            return round($snapshotBasePrice, 2);
        }

        $dailyRate = $snapshotBasePrice / $totalCycleDays;

        return round($dailyRate * $activeDays, 2);
    }

    /**
     * Calculate prorated included usage allowance for a segment.
     */
    public function calculateProratedIncludedAllowance(int $snapshotIncludedUnits, int $activeDays, int $totalCycleDays): int
    {
        if ($activeDays >= $totalCycleDays) {
            return $snapshotIncludedUnits;
        }

        return (int) floor($snapshotIncludedUnits * ($activeDays / $totalCycleDays));
    }

    /**
     * Calculate overage charge for a segment.
     */
    public function calculateOverageCharge(int $totalUsage, int $includedAllowance, float $snapshotOverageRate): array
    {
        $billableUnits = (int) max(0, $totalUsage - $includedAllowance);
        $amount = round($billableUnits * $snapshotOverageRate, 2);

        return [
            'billable_units' => $billableUnits,
            'amount' => $amount,
        ];
    }

    /**
     * Calculate billing items for a specific subscription segment.
     */
    protected function calculateSegmentBillingData(
        Subscription $subscription,
        SubscriptionSegment $segment,
        Carbon $cycleStart,
        Carbon $cycleEnd,
        int $totalCycleDays
    ): array {
        $activeData = $this->calculateSegmentActiveDays($segment, $cycleStart, $cycleEnd);
        $activeDays = $activeData['active_days'];
        $segStart = $activeData['start'];
        $segEnd = $activeData['end'];

        $snapshotBasePrice = (float) $segment->snapshot_base_price;
        $snapshotIncludedUnits = (int) $segment->snapshot_included_usage_units;
        $snapshotOverageRate = (float) $segment->snapshot_overage_rate_per_unit;

        // 1. Prorated Base Price
        $baseCharge = $this->calculateProratedBaseCharge($snapshotBasePrice, $activeDays, $totalCycleDays);

        // 2. Prorated Included Allowance
        $includedAllowance = $this->calculateProratedIncludedAllowance($snapshotIncludedUnits, $activeDays, $totalCycleDays);

        // 3. Sum daily usage for this segment within effective segment window
        $totalUsage = (int) DailyUsage::where('subscription_id', $subscription->id)
            ->where('subscription_segment_id', $segment->id)
            ->where('usage_date', '>=', $segStart->toDateString())
            ->where('usage_date', '<=', $segEnd->toDateString())
            ->sum('total_usage_units');

        // 4. Calculate Overage
        $overageData = $this->calculateOverageCharge($totalUsage, $includedAllowance, $snapshotOverageRate);

        $planName = $segment->plan?->name ?? 'Subscription Plan';
        $items = [];

        // Base Fee Line Item
        $items[] = [
            'subscription_segment_id' => $segment->id,
            'type' => 'base_fee',
            'description' => sprintf('Base Subscription Fee — %s (%s to %s)', $planName, $segStart->format('Y-m-d'), $segEnd->format('Y-m-d')),
            'quantity' => 1,
            'unit_price' => $baseCharge,
            'amount' => $baseCharge,
        ];

        // Overage Line Item (if overage usage units exist)
        if ($overageData['billable_units'] > 0) {
            $items[] = [
                'subscription_segment_id' => $segment->id,
                'type' => 'overage',
                'description' => sprintf('Usage Overage — %s (%d units @ %s/unit)', $planName, $overageData['billable_units'], number_format($snapshotOverageRate, 4)),
                'quantity' => $overageData['billable_units'],
                'unit_price' => $snapshotOverageRate,
                'amount' => $overageData['amount'],
            ];
        }

        return $items;
    }
}
