<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Commerce\CreateProductRequest;
use App\Http\Requests\Commerce\DeleteProductRequest;
use App\Http\Requests\Commerce\ListProductsRequest;
use App\Http\Requests\Commerce\ShowProductRequest;
use App\Http\Requests\Commerce\UpdateProductRequest;
use App\Http\Resources\Commerce\ProductResource;
use App\Services\Commerce\ProductService;
use App\Support\Http\Commerce\CommerceRequestMapper;
use Illuminate\Http\JsonResponse;

class ProductController extends BaseApiController
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function index(ListProductsRequest $request): JsonResponse
    {
        $paginator = $this->productService->list($request->perPage());

        return $this->respondPaginated(
            ProductResource::collection($paginator),
            $paginator,
        );
    }

    public function store(CreateProductRequest $request): JsonResponse
    {
        $product = $this->productService->createProduct(
            CommerceRequestMapper::toCreateProductData($request->toDto(), $request->user(), $request->ip()),
        );

        return $this->respondCreated(new ProductResource($product));
    }

    public function show(ShowProductRequest $request): JsonResponse
    {
        return $this->respondResource(new ProductResource($request->routeProduct()));
    }

    public function update(UpdateProductRequest $request): JsonResponse
    {
        $updated = $this->productService->updateProduct(
            $request->routeProduct(),
            CommerceRequestMapper::toUpdateProductData($request->toDto(), $request->user(), $request->ip()),
        );

        return $this->respondResource(new ProductResource($updated));
    }

    public function destroy(DeleteProductRequest $request): JsonResponse
    {
        $this->productService->deleteProduct($request->routeProduct(), $request->user(), $request->ip());

        return $this->respondPlainMessage('Product deleted successfully.');
    }
}
