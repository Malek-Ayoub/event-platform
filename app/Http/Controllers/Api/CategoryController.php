<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Events\CreateCategoryRequest;
use App\Http\Requests\Events\DeleteCategoryRequest;
use App\Http\Requests\Events\ListCategoriesRequest;
use App\Http\Requests\Events\ShowCategoryRequest;
use App\Http\Requests\Events\UpdateCategoryRequest;
use App\Http\Resources\Events\CategoryResource;
use App\Services\Events\CategoryService;
use App\Support\Http\Events\EventRequestMapper;
use Illuminate\Http\JsonResponse;

class CategoryController extends BaseApiController
{
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    public function index(ListCategoriesRequest $request): JsonResponse
    {
        $paginator = $this->categoryService->list($request->perPage());

        return $this->respondPaginated(
            CategoryResource::collection($paginator),
            $paginator,
        );
    }

    public function store(CreateCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->createCategory(
            EventRequestMapper::toCreateCategoryData($request->toDto(), $request->user(), $request->ip()),
        );

        return $this->respondCreated(new CategoryResource($category));
    }

    public function show(ShowCategoryRequest $request): JsonResponse
    {
        return $this->respondResource(new CategoryResource($request->routeCategory()));
    }

    public function update(UpdateCategoryRequest $request): JsonResponse
    {
        $updated = $this->categoryService->updateCategory(
            $request->routeCategory(),
            EventRequestMapper::toUpdateCategoryData($request->toDto(), $request->user(), $request->ip()),
        );

        return $this->respondResource(new CategoryResource($updated));
    }

    public function destroy(DeleteCategoryRequest $request): JsonResponse
    {
        $this->categoryService->deleteCategory(
            $request->routeCategory(),
            $request->user(),
            $request->ip(),
        );

        return $this->respondPlainMessage('Category deleted successfully.');
    }
}
