<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(
        mixed $data = null,
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $payload = ['data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function resource(JsonResource $resource, int $status = 200): JsonResponse
    {
        return $resource->response()->setStatusCode($status);
    }

    public static function created(JsonResource $resource): JsonResponse
    {
        return self::resource($resource, 201);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function message(string $message, int $status = 200, array $meta = []): JsonResponse
    {
        return self::success(['message' => $message], $status, $meta);
    }

    public static function paginated(
        ResourceCollection $collection,
        LengthAwarePaginator $paginator,
    ): JsonResponse {
        if ($collection->resource instanceof LengthAwarePaginator) {
            return $collection->response();
        }

        return $collection
            ->additional([
                'meta' => self::paginationMeta($paginator),
                'links' => self::paginationLinks($paginator),
            ])
            ->response();
    }

    /**
     * @return array<string, mixed>
     */
    public static function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'path' => $paginator->path(),
            'per_page' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public static function paginationLinks(LengthAwarePaginator $paginator): array
    {
        return [
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];
    }

    /**
     * @param  array<string, list<string>>|null  $errors
     */
    public static function error(
        string $message,
        int $status,
        ?array $errors = null,
    ): JsonResponse {
        $payload = ['message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    public static function plainMessage(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
