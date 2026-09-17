<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Services\MerchantDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the merchant dashboard web view.
     */
    public function index(Request $request, MerchantDashboardService $dashboardService): View
    {
        $merchants = Merchant::orderBy('name')->get();
        $merchantId = $request->query('merchant_id');

        $merchant = null;
        $dashboardData = null;
        $errorMessage = null;

        if ($merchants->isEmpty()) {
            $errorMessage = 'No merchants found in the system. Please run seeders or create a merchant.';
            return view('dashboard', compact('merchants', 'merchant', 'dashboardData', 'errorMessage'));
        }

        if ($merchantId !== null && $merchantId !== '') {
            if (! is_numeric($merchantId)) {
                $errorMessage = 'Invalid merchant identifier specified.';
            } else {
                $merchant = $merchants->firstWhere('id', (int) $merchantId);
                if (! $merchant) {
                    $errorMessage = "Merchant with ID {$merchantId} was not found.";
                }
            }
        } else {
            // Default to the first available merchant
            $merchant = $merchants->first();
        }

        if ($merchant && ! $errorMessage) {
            try {
                $dashboardData = $dashboardService->getDashboardData($merchant->id);
            } catch (\Throwable $e) {
                $errorMessage = 'Unable to load dashboard metrics. Please try again later.';
            }
        }

        return view('dashboard', compact('merchants', 'merchant', 'dashboardData', 'errorMessage'));
    }
}
