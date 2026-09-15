<?php

namespace App\Jobs;

use App\Services\UsageAggregationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class AggregateDailyUsage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?int $merchantId = null,
        public ?int $customerId = null,
        public ?string $fromDate = null,
        public ?string $toDate = null,
        public int $chunkSize = 1000
    ) {}

    /**
     * Execute the job.
     */
    public function handle(UsageAggregationService $service): void
    {
        $service->aggregate(
            merchantId: $this->merchantId,
            customerId: $this->customerId,
            fromDate: $this->fromDate,
            toDate: $this->toDate,
            chunkSize: $this->chunkSize
        );
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        // Custom failure handling log or alert notification
    }
}
