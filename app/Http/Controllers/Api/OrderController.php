<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Orders\CreateOrderRequest;
use App\Http\Requests\Orders\ListOrdersRequest;
use App\Http\Requests\Orders\ShowOrderRequest;
use App\Http\Resources\Orders\OrderResource;
use App\Services\Orders\OrderService;
use App\Support\Http\Orders\OrderRequestMapper;
use Illuminate\Http\JsonResponse;

class OrderController extends BaseApiController
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function index(ListOrdersRequest $request): JsonResponse
    {
        $paginator = $this->orderService->list(
            $request->perPage(),
            $request->eventId(),
            $request->status(),
        );

        return $this->respondPaginated(
            OrderResource::collection($paginator),
            $paginator,
        );
    }

    public function store(CreateOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->createOrder(
            OrderRequestMapper::toCreateOrderData($request->toDto(), $request->user(), $request->ip()),
        );

        return $this->respondCreated(new OrderResource($order));
    }

    public function show(ShowOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->getOrder($request->routeOrder());

        return $this->respondResource(new OrderResource($order));
    }
}
