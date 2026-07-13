<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Reports\AdminReportRequest;
use App\Services\Reports\AdminReportService;
use App\Services\Reports\Data\AdminReportFilter;
use App\Support\Http\Reports\AdminReportApiResponse;
use Illuminate\Http\JsonResponse;

class AdminReportController extends BaseApiController
{
    public function __construct(
        private readonly AdminReportService $reportService,
    ) {}

    public function show(AdminReportRequest $request): JsonResponse
    {
        $report = $this->reportService->build(new AdminReportFilter(
            range: $request->dateRange(),
            limit: $request->limit(),
        ));

        return AdminReportApiResponse::report($report);
    }
}
