<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * JSON health check: app + database status.
     */
    public function __invoke(): JsonResponse
    {
        $dbStatus = 'ok';
        $dbError = null;

        try {
            DB::select('SELECT 1');
        } catch (\Throwable $e) {
            $dbStatus = 'error';
            $dbError = $e->getMessage();
        }

        return response()->json([
            'app' => config('app.name'),
            'status' => $dbStatus === 'ok' ? 'ok' : 'degraded',
            'database' => $dbStatus,
            'database_error' => $dbError,
            'time' => now()->toIso8601String(),
        ], $dbStatus === 'ok' ? 200 : 503);
    }
}
