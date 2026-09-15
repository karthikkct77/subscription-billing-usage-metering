<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with demo merchants, customers, subscriptions, and usage data.
     */
    public function run(): void
    {
        $now = now();
        $currentMonthStart = $now->copy()->startOfMonth();
        $currentMonthEnd = $now->copy()->endOfMonth();
        $prevMonthMiddle = $now->copy()->subMonth()->startOfMonth()->addDays(10);

        // ==========================================
        // 1. Merchant 1: Acme SaaS Solutions
        // ==========================================
        $merchant1 = Merchant::create([
            'name' => 'Acme SaaS Solutions',
            'email' => 'billing@acme-saas.com',
            'status' => 'active',
        ]);

        $plan1 = Plan::create([
            'merchant_id' => $merchant1->id,
            'name' => 'Pro Plan',
            'code' => 'pro-plan',
            'base_price' => 99.00,
            'billing_cycle' => 'monthly',
            'included_usage_units' => 1000,
            'overage_rate_per_unit' => 0.10,
            'is_active' => true,
        ]);

        $customersData1 = [
            ['name' => 'Global Tech Inc', 'email' => 'globaltech@example.com', 'curr_usage' => 4500, 'prev_usage' => 4000],
            ['name' => 'Apex Logistics', 'email' => 'apex@example.com', 'curr_usage' => 3200, 'prev_usage' => 3000],
            ['name' => 'Starlight Media', 'email' => 'starlight@example.com', 'curr_usage' => 2100, 'prev_usage' => 2000],
            ['name' => 'Nexus Data Labs', 'email' => 'nexus@example.com', 'curr_usage' => 1800, 'prev_usage' => 1700],
            ['name' => 'Quantum Systems', 'email' => 'quantum@example.com', 'curr_usage' => 1200, 'prev_usage' => 1100],
            ['name' => 'Horizon Ventures', 'email' => 'horizon@example.com', 'curr_usage' => 800, 'prev_usage' => 750],
            ['name' => 'Legacy Enterprises', 'email' => 'legacy@example.com', 'curr_usage' => 800, 'prev_usage' => 5000], // >50% MoM Drop
            ['name' => 'Beta Analytics', 'email' => 'beta@example.com', 'curr_usage' => 400, 'prev_usage' => 2500],   // >50% MoM Drop
        ];

        foreach ($customersData1 as $data) {
            $customer = Customer::create([
                'merchant_id' => $merchant1->id,
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            $sub = Subscription::create([
                'merchant_id' => $merchant1->id,
                'customer_id' => $customer->id,
                'current_plan_id' => $plan1->id,
                'starts_at' => $currentMonthStart,
                'ends_at' => null,
                'current_period_starts_at' => $currentMonthStart,
                'current_period_ends_at' => $currentMonthEnd,
                'status' => 'active',
            ]);

            SubscriptionSegment::create([
                'subscription_id' => $sub->id,
                'plan_id' => $plan1->id,
                'starts_at' => $currentMonthStart,
                'ends_at' => null,
                'snapshot_base_price' => 99.00,
                'snapshot_included_usage_units' => 1000,
                'snapshot_overage_rate_per_unit' => 0.10,
            ]);

            // Add previous month usage
            if ($data['prev_usage'] > 0) {
                DailyUsage::create([
                    'merchant_id' => $merchant1->id,
                    'customer_id' => $customer->id,
                    'subscription_id' => $sub->id,
                    'usage_date' => $prevMonthMiddle->toDateString(),
                    'total_usage_units' => $data['prev_usage'],
                ]);
            }

            // Add current month usage
            if ($data['curr_usage'] > 0) {
                DailyUsage::create([
                    'merchant_id' => $merchant1->id,
                    'customer_id' => $customer->id,
                    'subscription_id' => $sub->id,
                    'usage_date' => $now->toDateString(),
                    'total_usage_units' => $data['curr_usage'],
                ]);
            }
        }

        // ==========================================
        // 2. Merchant 2: CloudScale API Services
        // ==========================================
        $merchant2 = Merchant::create([
            'name' => 'CloudScale API Services',
            'email' => 'admin@cloudscale-api.io',
            'status' => 'active',
        ]);

        $plan2 = Plan::create([
            'merchant_id' => $merchant2->id,
            'name' => 'Enterprise API',
            'code' => 'ent-api',
            'base_price' => 499.00,
            'billing_cycle' => 'monthly',
            'included_usage_units' => 10000,
            'overage_rate_per_unit' => 0.05,
            'is_active' => true,
        ]);

        $customersData2 = [
            ['name' => 'MegaCorp Cybernetics', 'email' => 'megacorp@example.com', 'curr_usage' => 25000, 'prev_usage' => 20000],
            ['name' => 'Hyperion Robotics', 'email' => 'hyperion@example.com', 'curr_usage' => 18000, 'prev_usage' => 15000],
            ['name' => 'Omni Consumer Products', 'email' => 'omni@example.com', 'curr_usage' => 12000, 'prev_usage' => 35000], // >50% MoM Drop
        ];

        foreach ($customersData2 as $data) {
            $customer = Customer::create([
                'merchant_id' => $merchant2->id,
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            $sub = Subscription::create([
                'merchant_id' => $merchant2->id,
                'customer_id' => $customer->id,
                'current_plan_id' => $plan2->id,
                'starts_at' => $currentMonthStart,
                'ends_at' => null,
                'current_period_starts_at' => $currentMonthStart,
                'current_period_ends_at' => $currentMonthEnd,
                'status' => 'active',
            ]);

            SubscriptionSegment::create([
                'subscription_id' => $sub->id,
                'plan_id' => $plan2->id,
                'starts_at' => $currentMonthStart,
                'ends_at' => null,
                'snapshot_base_price' => 499.00,
                'snapshot_included_usage_units' => 10000,
                'snapshot_overage_rate_per_unit' => 0.05,
            ]);

            if ($data['prev_usage'] > 0) {
                DailyUsage::create([
                    'merchant_id' => $merchant2->id,
                    'customer_id' => $customer->id,
                    'subscription_id' => $sub->id,
                    'usage_date' => $prevMonthMiddle->toDateString(),
                    'total_usage_units' => $data['prev_usage'],
                ]);
            }

            if ($data['curr_usage'] > 0) {
                DailyUsage::create([
                    'merchant_id' => $merchant2->id,
                    'customer_id' => $customer->id,
                    'subscription_id' => $sub->id,
                    'usage_date' => $now->toDateString(),
                    'total_usage_units' => $data['curr_usage'],
                ]);
            }
        }
    }
}
