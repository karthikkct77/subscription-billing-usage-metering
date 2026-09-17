<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\BillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

class ProcessSubscriptionBilling implements ShouldQueue
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
        public int $subscriptionId,
        public ?string $periodStartsAt = null,
        public ?string $periodEndsAt = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(BillingService $billingService): void
    {
        $subscription = Subscription::find($this->subscriptionId);

        if (! $subscription) {
            return;
        }

        $startsAt = $this->periodStartsAt ? Carbon::parse($this->periodStartsAt) : null;
        $endsAt = $this->periodEndsAt ? Carbon::parse($this->periodEndsAt) : null;

        $billingService->generateInvoiceForSubscription(
            subscription: $subscription,
            periodStartsAt: $startsAt,
            periodEndsAt: $endsAt
        );
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        // Log failure context
    }
}
