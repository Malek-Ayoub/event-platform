<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

abstract class BaseApiController extends Controller
{
    protected function respondResource(JsonResource $resource, int $status = 200): JsonResponse
    {
        return ApiResponse::resource($resource, $status);
    }

    protected function respondCreated(JsonResource $resource): JsonResponse
    {
        return ApiResponse::created($resource);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function respondSuccess(mixed $data = null, int $status = 200, array $meta = []): JsonResponse
    {
        return ApiResponse::success($data, $status, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function respondMessage(string $message, int $status = 200, array $meta = []): JsonResponse
    {
        return ApiResponse::message($message, $status, $meta);
    }

    protected function respondPaginated(
        ResourceCollection $collection,
        LengthAwarePaginator $paginator,
    ): JsonResponse {
        return ApiResponse::paginated($collection, $paginator);
    }

    protected function respondPlainMessage(string $message, int $status = 200): JsonResponse
    {
        return ApiResponse::plainMessage($message, $status);
    }
}
