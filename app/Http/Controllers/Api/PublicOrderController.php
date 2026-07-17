<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Orders\CreatePublicOrderDTO;
use App\Http\Requests\Orders\CreatePublicOrderRequest;
use App\Http\Resources\Orders\PublicOrderResource;
use App\Services\Orders\OrderService;
use App\Support\Http\Orders\OrderRequestMapper;
use Illuminate\Http\JsonResponse;

class PublicOrderController extends BaseApiController
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function store(CreatePublicOrderRequest $request): JsonResponse
    {
        /** @var CreatePublicOrderDTO $dto */
        $dto = $request->toDto();

        $order = $this->orderService->createOrder(
            OrderRequestMapper::toGuestCreateOrderData($dto, $request->ip()),
        );

        return $this->respondCreated(new PublicOrderResource($order));
    }
}
