<?php

namespace App\Support\Http\Reports;

use App\Services\Reports\Data\OrganizerReportData;
use Illuminate\Http\JsonResponse;

final class OrganizerReportApiResponse
{
    public static function report(OrganizerReportData $report, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $report->toArray(),
        ], $status);
    }
}
