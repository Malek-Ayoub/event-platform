<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\TaxRates\CreateTaxRateRequest;
use App\Http\Requests\TaxRates\DeleteTaxRateRequest;
use App\Http\Requests\TaxRates\ListTaxRatesRequest;
use App\Http\Requests\TaxRates\ShowTaxRateRequest;
use App\Http\Requests\TaxRates\UpdateTaxRateRequest;
use App\Http\Resources\TaxRates\TaxRateResource;
use App\Services\TaxRates\TaxRateService;
use App\Support\Http\TaxRates\TaxRateRequestMapper;
use Illuminate\Http\JsonResponse;

class TaxRateController extends BaseApiController
{
    public function __construct(
        private readonly TaxRateService $taxRateService,
    ) {}

    public function index(ListTaxRatesRequest $request): JsonResponse
    {
        $paginator = $this->taxRateService->list($request->perPage());

        return $this->respondPaginated(
            TaxRateResource::collection($paginator),
            $paginator,
        );
    }

    public function store(CreateTaxRateRequest $request): JsonResponse
    {
        $taxRate = $this->taxRateService->createTaxRate(
            TaxRateRequestMapper::toCreateTaxRateData($request->toDto(), $request->user(), $request->ip()),
        );

        return $this->respondCreated(new TaxRateResource($taxRate));
    }

    public function show(ShowTaxRateRequest $request): JsonResponse
    {
        return $this->respondResource(new TaxRateResource($request->routeTaxRate()));
    }

    public function update(UpdateTaxRateRequest $request): JsonResponse
    {
        $updated = $this->taxRateService->updateTaxRate(
            $request->routeTaxRate(),
            TaxRateRequestMapper::toUpdateTaxRateData($request->toDto(), $request->user(), $request->ip()),
        );

        return $this->respondResource(new TaxRateResource($updated));
    }

    public function destroy(DeleteTaxRateRequest $request): JsonResponse
    {
        $this->taxRateService->deleteTaxRate(
            $request->routeTaxRate(),
            $request->user(),
            $request->ip(),
        );

        return $this->respondPlainMessage('Tax rate deleted successfully.');
    }
}
