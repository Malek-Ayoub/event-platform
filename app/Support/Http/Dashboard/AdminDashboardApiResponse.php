<?php

namespace App\Support\Http\Dashboard;

use App\Services\Dashboard\Data\AdminDashboardData;
use Illuminate\Http\JsonResponse;

final class AdminDashboardApiResponse
{
    public static function dashboard(AdminDashboardData $dashboard, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $dashboard->toArray(),
        ], $status);
    }
}
