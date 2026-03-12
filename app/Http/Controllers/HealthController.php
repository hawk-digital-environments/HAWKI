<?php

namespace App\Http\Controllers;

use App\Services\System\Health\HealthChecker;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Perform a comprehensive health check of the application.
     * This endpoint is designed to be used by Docker health checks.
     *
     * @param HealthChecker $checker
     * @return JsonResponse
     */
    public function check(HealthChecker $checker): JsonResponse
    {
        $result = $checker->check();

        return response()->json([
            'status' => $result->isOk() ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $result->results,
        ], $result->isOk() ? 200 : 503);
    }
}
