<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSubscriptionBilling;
use App\Models\Subscription;
use App\Services\BillingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateInvoicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:generate
                            {--date= : Filter subscriptions ending on or before date (YYYY-MM-DD)}
                            {--subscription= : Process a specific Subscription ID}
                            {--queue : Dispatch billing process to queue instead of synchronous execution}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate cycle-end invoices for subscriptions';

    /**
     * Execute the console command.
     */
    public function handle(BillingService $billingService): int
    {
        $targetDate = $this->option('date') ? Carbon::parse($this->option('date'))->endOfDay() : now()->endOfDay();
        $subscriptionId = $this->option('subscription') ? (int) $this->option('subscription') : null;
        $shouldQueue = (bool) $this->option('queue');

        $query = Subscription::query();

        if ($subscriptionId) {
            $query->where('id', $subscriptionId);
        } else {
            $query->where('status', 'active')
                ->where('current_period_ends_at', '<=', $targetDate);
        }

        $subscriptions = $query->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No subscriptions due for billing invoice generation.');

            return self::SUCCESS;
        }

        $this->info("Found {$subscriptions->count()} subscription(s) for cycle-end billing.");

        $count = 0;

        foreach ($subscriptions as $subscription) {
            if ($shouldQueue) {
                ProcessSubscriptionBilling::dispatch(
                    subscriptionId: $subscription->id,
                    periodStartsAt: $subscription->current_period_starts_at->toDateTimeString(),
                    periodEndsAt: $subscription->current_period_ends_at->toDateTimeString()
                );
            } else {
                $invoice = $billingService->generateInvoiceForSubscription($subscription);
                if ($invoice) {
                    $count++;
                }
            }
        }

        if ($shouldQueue) {
            $this->info("Dispatched {$subscriptions->count()} billing job(s) to the queue.");
        } else {
            $this->info("Successfully generated {$count} invoice(s).");
        }

        return self::SUCCESS;
    }
}
