<?php

namespace App\Console\Commands;

use App\Jobs\AggregateDailyUsage;
use App\Services\UsageAggregationService;
use Illuminate\Console\Command;

class AggregateUsageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usage:aggregate
                            {--merchant= : Filter aggregation by Merchant ID}
                            {--customer= : Filter aggregation by Customer ID}
                            {--from= : Filter aggregation start date (YYYY-MM-DD)}
                            {--to= : Filter aggregation end date (YYYY-MM-DD)}
                            {--queue : Dispatch aggregation job to queue instead of synchronous execution}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregate raw usage events into daily customer usage totals';

    /**
     * Execute the console command.
     */
    public function handle(UsageAggregationService $service): int
    {
        $merchantId = $this->option('merchant') ? (int) $this->option('merchant') : null;
        $customerId = $this->option('customer') ? (int) $this->option('customer') : null;
        $fromDate = $this->option('from') ?: null;
        $toDate = $this->option('to') ?: null;
        $shouldQueue = (bool) $this->option('queue');

        if ($shouldQueue) {
            AggregateDailyUsage::dispatch(
                merchantId: $merchantId,
                customerId: $customerId,
                fromDate: $fromDate,
                toDate: $toDate
            );

            $this->info('Daily usage aggregation job queued successfully.');

            return self::SUCCESS;
        }

        $this->info('Starting synchronous daily usage aggregation...');

        $updatedCount = $service->aggregate(
            merchantId: $merchantId,
            customerId: $customerId,
            fromDate: $fromDate,
            toDate: $toDate
        );

        $this->info("Daily usage aggregation completed. Updated/created {$updatedCount} daily records.");

        return self::SUCCESS;
    }
}
