<?php

namespace App\Providers;

use App\Models\Plan;
use App\Observers\PlanObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Plan::observe(PlanObserver::class);

        RateLimiter::for('usage', function (Request $request) {
            $key = $request->input('merchant_id') ?: $request->ip();

            return Limit::perMinute(600)->by((string) $key);
        });
    }
}
