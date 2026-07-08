<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Commerce\CreateCouponRequest;
use App\Http\Requests\Commerce\DeleteCouponRequest;
use App\Http\Requests\Commerce\ListCouponsRequest;
use App\Http\Requests\Commerce\ShowCouponRequest;
use App\Http\Requests\Commerce\UpdateCouponRequest;
use App\Http\Resources\Commerce\CouponResource;
use App\Services\Commerce\CouponService;
use App\Support\Http\Commerce\CommerceRequestMapper;
use Illuminate\Http\JsonResponse;

class CouponController extends BaseApiController
{
    public function __construct(
        private readonly CouponService $couponService,
    ) {}

    public function index(ListCouponsRequest $request): JsonResponse
    {
        $paginator = $this->couponService->list($request->perPage());

        return $this->respondPaginated(
            CouponResource::collection($paginator),
            $paginator,
        );
    }

    public function store(CreateCouponRequest $request): JsonResponse
    {
        $coupon = $this->couponService->createCoupon(
            CommerceRequestMapper::toCreateCouponData($request->toDto(), $request->user(), $request->ip()),
        );

        return $this->respondCreated(new CouponResource($coupon));
    }

    public function show(ShowCouponRequest $request): JsonResponse
    {
        return $this->respondResource(new CouponResource($request->routeCoupon()));
    }

    public function update(UpdateCouponRequest $request): JsonResponse
    {
        $updated = $this->couponService->updateCoupon(
            $request->routeCoupon(),
            CommerceRequestMapper::toUpdateCouponData($request->toDto(), $request->user(), $request->ip()),
        );

        return $this->respondResource(new CouponResource($updated));
    }

    public function destroy(DeleteCouponRequest $request): JsonResponse
    {
        $this->couponService->deleteCoupon($request->routeCoupon(), $request->user(), $request->ip());

        return $this->respondPlainMessage('Coupon deleted successfully.');
    }
}
