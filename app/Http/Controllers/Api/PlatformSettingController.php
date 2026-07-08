<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\PlatformSettings\ShowPlatformSettingRequest;
use App\Http\Requests\PlatformSettings\UpdatePlatformSettingRequest;
use App\Http\Resources\PlatformSettings\PlatformSettingResource;
use App\Services\PlatformSettings\PlatformSettingService;
use App\Support\Http\PlatformSettings\PlatformSettingRequestMapper;
use Illuminate\Http\JsonResponse;

class PlatformSettingController extends BaseApiController
{
    public function __construct(
        private readonly PlatformSettingService $platformSettingService,
    ) {}

    public function show(ShowPlatformSettingRequest $request): JsonResponse
    {
        return $this->respondResource(
            new PlatformSettingResource($this->platformSettingService->get()),
        );
    }

    public function update(UpdatePlatformSettingRequest $request): JsonResponse
    {
        $updated = $this->platformSettingService->update(
            PlatformSettingRequestMapper::toUpdatePlatformSettingData(
                $request->toDto(),
                $request->user(),
                $request->ip(),
            ),
        );

        return $this->respondResource(new PlatformSettingResource($updated));
    }
}
