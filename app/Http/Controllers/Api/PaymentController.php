<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Payments\CompletePaymentRequest;
use App\Http\Requests\Payments\FailPaymentRequest;
use App\Http\Requests\Payments\InitiatePaymentRequest;
use App\Http\Requests\Payments\ListPaymentsRequest;
use App\Http\Requests\Payments\ShowPaymentRequest;
use App\Http\Resources\Payments\PaymentTransactionResource;
use App\Services\Payments\PaymentService;
use App\Support\Http\Payments\PaymentRequestMapper;
use Illuminate\Http\JsonResponse;

class PaymentController extends BaseApiController
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function index(ListPaymentsRequest $request): JsonResponse
    {
        $paginator = $this->paymentService->list(
            $request->perPage(),
            $request->orderId(),
            $request->status(),
        );

        return $this->respondPaginated(
            PaymentTransactionResource::collection($paginator),
            $paginator,
        );
    }

    public function store(InitiatePaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->initiatePayment(
            PaymentRequestMapper::toInitiatePaymentData($request->toDto(), $request->user(), $request->ip()),
        );

        return $this->respondCreated(new PaymentTransactionResource($payment));
    }

    public function show(ShowPaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->getPayment($request->routePaymentTransaction());

        return $this->respondResource(new PaymentTransactionResource($payment));
    }

    public function complete(CompletePaymentRequest $request): JsonResponse
    {
        $completed = $this->paymentService->completePayment(
            PaymentRequestMapper::toCompletePaymentData(
                $request->routePaymentTransaction(),
                $request->toDto(),
                $request->user(),
                $request->ip(),
            ),
        );

        return $this->respondResource(new PaymentTransactionResource($completed));
    }

    public function fail(FailPaymentRequest $request): JsonResponse
    {
        $failed = $this->paymentService->failPayment(
            PaymentRequestMapper::toFailPaymentData(
                $request->routePaymentTransaction(),
                $request->toDto(),
                $request->user(),
                $request->ip(),
            ),
        );

        return $this->respondResource(new PaymentTransactionResource($failed));
    }
}
