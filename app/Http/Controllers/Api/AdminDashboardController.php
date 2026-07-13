<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Dashboard\AdminDashboardRequest;
use App\Services\Dashboard\AdminDashboardService;
use App\Support\Http\Dashboard\AdminDashboardApiResponse;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends BaseApiController
{
    public function __construct(
        private readonly AdminDashboardService $dashboardService,
    ) {}

    public function show(AdminDashboardRequest $request): JsonResponse
    {
        return AdminDashboardApiResponse::dashboard(
            $this->dashboardService->build(),
        );
    }
}
