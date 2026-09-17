<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Check application health status.
     */
    public function __invoke(): JsonResponse
    {
        return $this->successResponse(
            data: [
                'status' => 'healthy',
                'timestamp' => now()->toIso8601String(),
            ],
            message: 'Application is operational'
        );
    }
}
