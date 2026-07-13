<?php

namespace App\Support\Http\Reports;

use App\Services\Reports\Data\AdminReportData;
use Illuminate\Http\JsonResponse;

final class AdminReportApiResponse
{
    public static function report(AdminReportData $report, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $report->toArray(),
        ], $status);
    }
}
