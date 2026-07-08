<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Commerce\CreateProductVariantRequest;
use App\Http\Requests\Commerce\DeleteProductVariantRequest;
use App\Http\Requests\Commerce\ListProductVariantsRequest;
use App\Http\Requests\Commerce\ShowProductVariantRequest;
use App\Http\Requests\Commerce\UpdateProductVariantRequest;
use App\Http\Resources\Commerce\ProductVariantResource;
use App\Services\Commerce\ProductVariantService;
use App\Support\Http\Commerce\CommerceRequestMapper;
use Illuminate\Http\JsonResponse;

class ProductVariantController extends BaseApiController
{
    public function __construct(
        private readonly ProductVariantService $productVariantService,
    ) {}

    public function index(ListProductVariantsRequest $request): JsonResponse
    {
        $paginator = $this->productVariantService->listForProduct($request->routeProduct(), $request->perPage());

        return $this->respondPaginated(
            ProductVariantResource::collection($paginator),
            $paginator,
        );
    }

    public function store(CreateProductVariantRequest $request): JsonResponse
    {
        $variant = $this->productVariantService->createProductVariant(
            CommerceRequestMapper::toCreateProductVariantData($request->toDto(), $request->user(), $request->ip()),
        );

        return $this->respondCreated(new ProductVariantResource($variant));
    }

    public function show(ShowProductVariantRequest $request): JsonResponse
    {
        return $this->respondResource(new ProductVariantResource($request->routeProductVariant()));
    }

    public function update(UpdateProductVariantRequest $request): JsonResponse
    {
        $updated = $this->productVariantService->updateProductVariant(
            $request->routeProductVariant(),
            CommerceRequestMapper::toUpdateProductVariantData($request->toDto(), $request->user(), $request->ip()),
        );

        return $this->respondResource(new ProductVariantResource($updated));
    }

    public function destroy(DeleteProductVariantRequest $request): JsonResponse
    {
        $this->productVariantService->deleteProductVariant(
            $request->routeProductVariant(),
            $request->user(),
            $request->ip(),
        );

        return $this->respondPlainMessage('Product variant deleted successfully.');
    }
}
