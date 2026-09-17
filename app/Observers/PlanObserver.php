<?php

namespace App\Observers;

use App\Models\Plan;
use App\Services\PlanPricingService;

class PlanObserver
{
    public function __construct(
        protected PlanPricingService $pricingService
    ) {}

    /**
     * Handle the Plan "saved" event.
     */
    public function saved(Plan $plan): void
    {
        $this->pricingService->invalidateCache($plan->id);
    }

    /**
     * Handle the Plan "deleted" event.
     */
    public function deleted(Plan $plan): void
    {
        $this->pricingService->invalidateCache($plan->id);
    }
}
