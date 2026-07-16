<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Events\ListPublicEventsRequest;
use App\Http\Requests\Events\ShowPublicEventRequest;
use App\Http\Resources\Events\PublicEventDetailResource;
use App\Http\Resources\Events\PublicEventListItemResource;
use App\Services\Events\PublishedEventCatalogService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

    public function show(ShowPublicEventRequest $request): JsonResponse
    {
        $item = $this->publishedEventCatalogService->findPublishedBySlug($request->slug());

        if ($item === null) {
            throw new NotFoundHttpException('Published event not found.');
        }

        return $this->respondResource(new PublicEventDetailResource($item));
    }
}
