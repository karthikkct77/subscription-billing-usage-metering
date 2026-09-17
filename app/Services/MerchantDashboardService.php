<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MerchantDashboardService
{
    /**
     * Get complete dashboard metrics for a merchant.
     */
    public function getDashboardData(int $merchantId, ?Carbon $now = null): array
    {
        $now = $now ?? now();

        return [
            'top_customers_by_usage' => $this->getTopCustomersByUsage($merchantId, $now),
            'projected_overage_revenue' => $this->getProjectedOverageRevenue($merchantId, $now),
            'churn_risk_customers' => $this->getChurnRiskCustomers($merchantId, $now),
            'daily_usage_trends' => $this->getDailyUsageTrends($merchantId, $now),
        ];
    }

    /**
     * Top 5 customers by usage in the current calendar month.
     */
    public function getTopCustomersByUsage(int $merchantId, ?Carbon $now = null, int $limit = 5): array
    {
        $now = $now ?? now();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();

        $rows = DailyUsage::where('merchant_id', $merchantId)
            ->whereBetween('usage_date', [$monthStart, $monthEnd])
            ->select('customer_id', DB::raw('SUM(total_usage_units) as total_usage_units'))
            ->groupBy('customer_id')
            ->orderByDesc('total_usage_units')
            ->limit($limit)
            ->with('customer:id,name,email')
            ->get();

        return $rows->map(function ($row) {
            return [
                'customer_id' => (int) $row->customer_id,
                'name' => (string) ($row->customer?->name ?? 'Unknown Customer'),
                'email' => (string) ($row->customer?->email ?? ''),
                'total_usage_units' => (int) $row->total_usage_units,
            ];
        })->all();
    }

    /**
     * Calculate projected overage revenue across active merchant subscriptions for the current cycle.
     */
    public function getProjectedOverageRevenue(int $merchantId, ?Carbon $now = null): float
    {
        $now = $now ?? now();

        $subscriptions = Subscription::where('merchant_id', $merchantId)
            ->where('status', 'active')
            ->with(['currentPlan', 'segments' => function ($q) use ($now) {
                $q->where('starts_at', '<=', $now)
                    ->where(function ($sq) use ($now) {
                        $sq->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                    })->orderBy('starts_at', 'desc');
            }])
            ->get();

        $totalProjectedRevenue = 0.0;

        foreach ($subscriptions as $sub) {
            $cycleStart = Carbon::parse($sub->current_period_starts_at)->startOfDay();
            $cycleEnd = Carbon::parse($sub->current_period_ends_at)->endOfDay();

            $totalSeconds = $cycleStart->diffInSeconds($cycleEnd->copy()->addSecond());
            $totalDays = (int) max(1, (int) round($totalSeconds / 86400));

            $effectiveNow = $now->greaterThan($cycleEnd) ? $cycleEnd : $now;
            $elapsedSeconds = $cycleStart->diffInSeconds($effectiveNow->copy()->endOfDay());
            $elapsedDays = (int) max(1, min($totalDays, (int) round($elapsedSeconds / 86400)));

            $currentDateStr = $effectiveNow->toDateString();
            $accumulatedUsage = (int) DailyUsage::where('subscription_id', $sub->id)
                ->whereBetween('usage_date', [$cycleStart->toDateString(), $currentDateStr])
                ->sum('total_usage_units');

            $projectedUsage = (int) floor(($accumulatedUsage / $elapsedDays) * $totalDays);

            // Active segment or current plan pricing
            $segment = $sub->segments->first();
            if ($segment) {
                $includedAllowance = (int) $segment->snapshot_included_usage_units;
                $overageRate = (float) $segment->snapshot_overage_rate_per_unit;
            } elseif ($sub->currentPlan) {
                $includedAllowance = (int) $sub->currentPlan->included_usage_units;
                $overageRate = (float) $sub->currentPlan->overage_rate_per_unit;
            } else {
                continue;
            }

            $projectedOverageUnits = (int) max(0, $projectedUsage - $includedAllowance);
            $projectedRevenue = round($projectedOverageUnits * $overageRate, 2);

            $totalProjectedRevenue += $projectedRevenue;
        }

        return round($totalProjectedRevenue, 2);
    }

    /**
     * Identify customers whose usage dropped > 50% month-over-month.
     */
    public function getChurnRiskCustomers(int $merchantId, ?Carbon $now = null): array
    {
        $now = $now ?? now();

        $currentMonthStart = $now->copy()->startOfMonth()->toDateString();
        $currentMonthEnd = $now->copy()->endOfMonth()->toDateString();

        $prevMonthStart = $now->copy()->subMonth()->startOfMonth()->toDateString();
        $prevMonthEnd = $now->copy()->subMonth()->endOfMonth()->toDateString();

        $currentMonthUsages = DailyUsage::where('merchant_id', $merchantId)
            ->whereBetween('usage_date', [$currentMonthStart, $currentMonthEnd])
            ->select('customer_id', DB::raw('SUM(total_usage_units) as total_units'))
            ->groupBy('customer_id')
            ->pluck('total_units', 'customer_id');

        $prevMonthUsages = DailyUsage::where('merchant_id', $merchantId)
            ->whereBetween('usage_date', [$prevMonthStart, $prevMonthEnd])
            ->select('customer_id', DB::raw('SUM(total_usage_units) as total_units'))
            ->groupBy('customer_id')
            ->pluck('total_units', 'customer_id');

        if ($prevMonthUsages->isEmpty()) {
            return [];
        }

        $customerIds = $prevMonthUsages->keys()->all();
        $customers = Customer::whereIn('id', $customerIds)
            ->where('merchant_id', $merchantId)
            ->get()
            ->keyBy('id');

        $churnRiskList = [];

        foreach ($prevMonthUsages as $customerId => $prevUnits) {
            $prevUnits = (int) $prevUnits;
            $currUnits = (int) ($currentMonthUsages[$customerId] ?? 0);

            if ($prevUnits <= 0) {
                continue;
            }

            // Condition: Current usage strictly less than 50% of previous month usage
            if ($currUnits < ($prevUnits * 0.50)) {
                $customer = $customers->get($customerId);

                if (! $customer) {
                    continue;
                }

                $dropPercentage = round((($prevUnits - $currUnits) / $prevUnits) * 100, 1);

                $churnRiskList[] = [
                    'customer_id' => (int) $customer->id,
                    'name' => (string) $customer->name,
                    'email' => (string) $customer->email,
                    'current_month_usage' => $currUnits,
                    'previous_month_usage' => $prevUnits,
                    'drop_percentage' => $dropPercentage,
                ];
            }
        }

        return $churnRiskList;
    }

    /**
     * Get daily usage trends for the current calendar month.
     */
    public function getDailyUsageTrends(int $merchantId, ?Carbon $now = null): array
    {
        $now = $now ?? now();
        $startOfMonth = $now->copy()->startOfMonth();
        $daysInMonth = $now->daysInMonth;

        $monthStart = $startOfMonth->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();

        $dailyUsages = DailyUsage::where('merchant_id', $merchantId)
            ->whereBetween('usage_date', [$monthStart, $monthEnd])
            ->select('usage_date', DB::raw('SUM(total_usage_units) as total_units'))
            ->groupBy('usage_date')
            ->pluck('total_units', 'usage_date');

        $trends = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateObj = $startOfMonth->copy()->day($day);
            $dateStr = $dateObj->toDateString();
            $label = $dateObj->format('M d');

            $trends[] = [
                'date' => $dateStr,
                'label' => $label,
                'usage_units' => (int) ($dailyUsages[$dateStr] ?? 0),
            ];
        }

        return $trends;
    }
}

