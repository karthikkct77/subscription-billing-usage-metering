<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Merchant Dashboard - Subscription Billing & Usage-Metering</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Styles -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: {
                            sans: ['Instrument Sans', 'sans-serif'],
                        }
                    }
                }
            }
        </script>
    @endif
</head>
<body class="bg-slate-50 text-slate-900 font-sans antialiased min-h-screen flex flex-col">
    <!-- Top Navigation Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                    S
                </div>
                <div>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Merchant Billing Dashboard</h1>
                    <p class="text-xs text-slate-500">Subscription Billing & Usage-Metering System</p>
                </div>
            </div>

            <!-- Merchant Context Switcher -->
            @if ($merchants && $merchants->count() > 0)
                <form action="{{ route('dashboard') }}" method="GET" class="flex items-center space-x-2">
                    <label for="merchant_id" class="text-xs font-semibold text-slate-600 uppercase tracking-wider whitespace-nowrap">
                        Merchant Context:
                    </label>
                    <select name="merchant_id" id="merchant_id" onchange="this.form.submit()"
                        class="bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-800 text-sm rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 block p-2 transition-colors font-medium">
                        @foreach ($merchants as $m)
                            <option value="{{ $m->id }}" {{ $merchant && $merchant->id === $m->id ? 'selected' : '' }}>
                                {{ $m->name }} (ID: {{ $m->id }})
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <!-- Error State Banner -->
        @if ($errorMessage)
            <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-md shadow-xs">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-amber-800">{{ $errorMessage }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if ($merchant && $dashboardData)
            <!-- Merchant Overview Banner -->
            <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 mb-2">
                        Active Tenant
                    </span>
                    <h2 class="text-2xl font-bold text-slate-900">{{ $merchant->name }}</h2>
                    <p class="text-sm text-slate-500 font-mono mt-0.5">{{ $merchant->email ?? 'No email on record' }}</p>
                </div>
                <div class="text-right border-t md:border-t-0 md:border-l border-slate-100 pt-3 md:pt-0 md:pl-6">
                    <div class="text-xs text-slate-500 uppercase tracking-wider">Billing Cycle Status</div>
                    <div class="text-sm font-semibold text-slate-800 mt-1">Current Calendar Month</div>
                    <div class="text-xs text-slate-400 mt-0.5">Automated Usage Aggregation Active</div>
                </div>
            </div>

            <!-- Top Metric Cards Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Card 1: Top Customer Usage -->
                <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-xs transition-all hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Top Customer Usage</span>
                        <div class="p-2 rounded-lg bg-indigo-50 text-indigo-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        @php
                            $topCustomerTotal = isset($dashboardData['top_customers_by_usage'][0])
                                ? number_format($dashboardData['top_customers_by_usage'][0]['total_usage_units'])
                                : '0';
                        @endphp
                        <span class="text-3xl font-extrabold text-slate-900">{{ $topCustomerTotal }}</span>
                        <span class="text-sm text-slate-500 ml-1">units</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">
                        #1 Customer:
                        <span class="font-medium text-slate-700">
                            {{ $dashboardData['top_customers_by_usage'][0]['name'] ?? 'N/A' }}
                        </span>
                    </p>
                </div>

                <!-- Card 2: Projected Overage Revenue -->
                <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-xs transition-all hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Projected Overage</span>
                        <div class="p-2 rounded-lg bg-emerald-50 text-emerald-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="text-3xl font-extrabold text-slate-900">
                            ${{ number_format($dashboardData['projected_overage_revenue'] ?? 0, 2) }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">Projected revenue for current cycle</p>
                </div>

                <!-- Card 3: At-Risk Customers -->
                <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-xs transition-all hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Usage Drop >50% MoM</span>
                        <div class="p-2 rounded-lg bg-rose-50 text-rose-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        @php
                            $riskCount = count($dashboardData['churn_risk_customers'] ?? []);
                        @endphp
                        <span class="text-3xl font-extrabold {{ $riskCount > 0 ? 'text-rose-600' : 'text-slate-900' }}">
                            {{ $riskCount }}
                        </span>
                        <span class="text-sm text-slate-500 ml-1">customers</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">
                        {{ $riskCount > 0 ? 'Requires attention for potential churn' : 'No significant churn risk detected' }}
                    </p>
                </div>
            </div>

            <!-- Content Grid: Top Customers & Projected Overage -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Top 5 Customers Table (Spans 2 columns) -->
                <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">Top 5 Customers by Usage</h3>
                            <p class="text-xs text-slate-500">Accumulated usage units for the current calendar month</p>
                        </div>
                        <span class="px-2.5 py-1 text-xs font-medium rounded-md bg-slate-100 text-slate-600">
                            Current Month
                        </span>
                    </div>

                    <div class="flex-1 overflow-x-auto">
                        @if (!empty($dashboardData['top_customers_by_usage']))
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/75 text-slate-500 text-xs font-semibold uppercase tracking-wider border-b border-slate-100">
                                        <th class="py-3.5 px-6 w-16 text-center">Rank</th>
                                        <th class="py-3.5 px-6">Customer</th>
                                        <th class="py-3.5 px-6 text-right">Usage Units</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-sm">
                                    @foreach ($dashboardData['top_customers_by_usage'] as $index => $customer)
                                        <tr class="hover:bg-slate-50/50 transition-colors">
                                            <td class="py-4 px-6 text-center">
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold
                                                    {{ $index === 0 ? 'bg-amber-100 text-amber-800 border border-amber-300' : ($index === 1 ? 'bg-slate-200 text-slate-700' : ($index === 2 ? 'bg-orange-100 text-orange-800' : 'bg-slate-100 text-slate-600')) }}">
                                                    {{ $index + 1 }}
                                                </span>
                                            </td>
                                            <td class="py-4 px-6">
                                                <div class="font-semibold text-slate-900">{{ $customer['name'] }}</div>
                                                @if (!empty($customer['email']))
                                                    <div class="text-xs text-slate-400 font-mono">{{ $customer['email'] }}</div>
                                                @endif
                                            </td>
                                            <td class="py-4 px-6 text-right font-bold text-slate-900 font-mono">
                                                {{ number_format($customer['total_usage_units']) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="p-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                </svg>
                                <h4 class="mt-3 text-sm font-semibold text-slate-700">No usage data found</h4>
                                <p class="mt-1 text-xs text-slate-500">There is no recorded daily usage for this merchant in the current month.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Projected Overage Revenue Card (Spans 1 column) -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-6 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
                            <h3 class="text-lg font-bold text-slate-900">Projected Overage</h3>
                            <span class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-100 text-emerald-800">
                                Real-Time Engine
                            </span>
                        </div>

                        <div class="text-center py-4 bg-slate-50 rounded-lg border border-slate-100 mb-6">
                            <div class="text-xs uppercase tracking-wider font-semibold text-slate-500">Cycle Projected Overage</div>
                            <div class="text-4xl font-black text-slate-900 mt-2">
                                ${{ number_format($dashboardData['projected_overage_revenue'] ?? 0, 2) }}
                            </div>
                            <div class="text-xs text-slate-400 mt-1">USD Currency</div>
                        </div>

                        <div class="space-y-3 text-xs text-slate-600">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Calculation Method:</span>
                                <span class="font-medium text-slate-800">Linear Daily Pro-rata</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Scope:</span>
                                <span class="font-medium text-slate-800">Active Subscriptions</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Allowance Snapshot:</span>
                                <span class="font-medium text-slate-800">Segment / Plan Rate</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 text-xs text-slate-400 leading-relaxed">
                        * Projected overage revenue is computed server-side based on cumulative daily usage extrapolated across active billing cycle days.
                    </div>
                </div>
            </div>

            <!-- Usage Drop >50% MoM Section -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Customers with Usage Drop >50% Month-over-Month</h3>
                        <p class="text-xs text-slate-500">Identifies active customers experiencing a steep decline in month-over-month usage</p>
                    </div>
                    <span class="px-2.5 py-1 text-xs font-medium rounded-md bg-rose-50 text-rose-700 border border-rose-200">
                        Churn Risk
                    </span>
                </div>

                <div class="overflow-x-auto">
                    @if (!empty($dashboardData['churn_risk_customers']))
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50/75 text-slate-500 text-xs font-semibold uppercase tracking-wider border-b border-slate-100">
                                    <th class="py-3.5 px-6">Customer</th>
                                    <th class="py-3.5 px-6 text-right">Previous Month Usage</th>
                                    <th class="py-3.5 px-6 text-right">Current Month Usage</th>
                                    <th class="py-3.5 px-6 text-center">MoM Drop %</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                @foreach ($dashboardData['churn_risk_customers'] as $risk)
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="py-4 px-6">
                                            <div class="font-semibold text-slate-900">{{ $risk['name'] }}</div>
                                            @if (!empty($risk['email']))
                                                <div class="text-xs text-slate-400 font-mono">{{ $risk['email'] }}</div>
                                            @endif
                                        </td>
                                        <td class="py-4 px-6 text-right font-medium text-slate-700 font-mono">
                                            {{ number_format($risk['previous_month_usage']) }}
                                        </td>
                                        <td class="py-4 px-6 text-right font-medium text-slate-900 font-mono">
                                            {{ number_format($risk['current_month_usage']) }}
                                        </td>
                                        <td class="py-4 px-6 text-center">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                                                -{{ $risk['drop_percentage'] }}%
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="p-10 text-center">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 mb-3">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <h4 class="text-sm font-semibold text-slate-800">No customers with &gt;50% usage drop.</h4>
                            <p class="text-xs text-slate-500 mt-1">All active customers are maintaining steady month-over-month usage volume.</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 py-4 mt-auto">
        <div class="max-w-7xl mx-auto px-4 text-center text-xs text-slate-400">
            Subscription Billing & Usage-Metering System &bull; Merchant Presentation Layer &bull; Powered by Laravel
        </div>
    </footer>
</body>
</html>
