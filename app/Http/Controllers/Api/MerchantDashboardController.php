<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Services\MerchantDashboardService;
use Illuminate\Http\JsonResponse;

class MerchantDashboardController extends Controller
{
    /**
     * Display merchant analytics dashboard.
     */
    public function show(int $id, MerchantDashboardService $dashboardService): JsonResponse
    {
        $merchant = Merchant::find($id);

        if (! $merchant) {
            return $this->errorResponse(
                message: 'Merchant not found.',
                statusCode: 404
            );
        }

        $data = $dashboardService->getDashboardData($merchant->id);

        return $this->successResponse(
            data: $data,
            message: 'Dashboard retrieved successfully.'
        );
    }
}
