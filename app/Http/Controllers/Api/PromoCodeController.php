<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Commerce\CreatePromoCodeRequest;
use App\Http\Requests\Commerce\DeletePromoCodeRequest;
use App\Http\Requests\Commerce\ListPromoCodesRequest;
use App\Http\Requests\Commerce\ShowPromoCodeRequest;
use App\Http\Requests\Commerce\UpdatePromoCodeRequest;
use App\Http\Resources\Commerce\PromoCodeResource;
use App\Services\Commerce\PromoCodeService;
use App\Support\Http\Commerce\CommerceRequestMapper;
use Illuminate\Http\JsonResponse;

class PromoCodeController extends BaseApiController
{
    public function __construct(
        private readonly PromoCodeService $promoCodeService,
    ) {}

    public function index(ListPromoCodesRequest $request): JsonResponse
    {
        $paginator = $this->promoCodeService->list($request->perPage());

        return $this->respondPaginated(
            PromoCodeResource::collection($paginator),
            $paginator,
        );
    }

    public function store(CreatePromoCodeRequest $request): JsonResponse
    {
        $promoCode = $this->promoCodeService->createPromoCode(
            CommerceRequestMapper::toCreatePromoCodeData($request->toDto(), $request->user(), $request->ip()),
        );

        return $this->respondCreated(new PromoCodeResource($promoCode));
    }

    public function show(ShowPromoCodeRequest $request): JsonResponse
    {
        return $this->respondResource(new PromoCodeResource($request->routePromoCode()));
    }

    public function update(UpdatePromoCodeRequest $request): JsonResponse
    {
        $updated = $this->promoCodeService->updatePromoCode(
            $request->routePromoCode(),
            CommerceRequestMapper::toUpdatePromoCodeData($request->toDto(), $request->user(), $request->ip()),
        );

        return $this->respondResource(new PromoCodeResource($updated));
    }

    public function destroy(DeletePromoCodeRequest $request): JsonResponse
    {
        $this->promoCodeService->deletePromoCode($request->routePromoCode(), $request->user(), $request->ip());

        return $this->respondPlainMessage('Promo code deleted successfully.');
    }
}
