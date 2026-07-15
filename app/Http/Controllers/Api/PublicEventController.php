<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Events\ListPublicEventsRequest;
use App\Http\Resources\Events\PublicEventListItemResource;
use App\Services\Events\PublishedEventCatalogService;
use Illuminate\Http\JsonResponse;

class PublicEventController extends BaseApiController
{
    public function __construct(
        private readonly PublishedEventCatalogService $publishedEventCatalogService,
    ) {}

    public function index(ListPublicEventsRequest $request): JsonResponse
    {
        $paginator = $this->publishedEventCatalogService->listPublished(
            $request->perPage(12),
            $request->sort(),
        );

        return $this->respondPaginated(
            PublicEventListItemResource::collection($paginator),
            $paginator,
        );
    }
}
