<?php

namespace App\Support\Http\Dashboard;

use App\Services\Dashboard\Data\OrganizerDashboardData;
use Illuminate\Http\JsonResponse;

final class OrganizerDashboardApiResponse
{
    public static function dashboard(OrganizerDashboardData $dashboard, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $dashboard->toArray(),
        ], $status);
    }
}
