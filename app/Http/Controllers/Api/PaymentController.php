<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Payments\InitiatePaymentRequest;
use App\Http\Requests\Payments\ListPaymentsRequest;
use App\Http\Requests\Payments\ShowPaymentRequest;
use App\Http\Requests\Payments\VerifyPaymentRequest;
use App\Http\Resources\Payments\PaymentInstructionResource;
use App\Http\Resources\Payments\PaymentTransactionResource;
use App\Services\Payments\PaymentInstructionService;
use App\Services\Payments\PaymentService;
use App\Services\Payments\PaymentVerificationService;
use App\Support\Http\Payments\PaymentRequestMapper;
use Illuminate\Http\JsonResponse;

class PaymentController extends BaseApiController
{
    public function __construct(
        private readonly PaymentInstructionService $paymentInstructionService,
        private readonly PaymentVerificationService $paymentVerificationService,
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
        $instructions = $this->paymentInstructionService->createInstructions(
            PaymentRequestMapper::toCreatePaymentInstructionsData($request->toDto(), $request->user(), $request->ip()),
        );

        return $this->respondCreated(new PaymentInstructionResource($instructions));
    }

    public function show(ShowPaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->getPayment($request->routePaymentTransaction());

        return $this->respondResource(new PaymentTransactionResource($payment));
    }

    public function verify(VerifyPaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentVerificationService->verify(
            PaymentRequestMapper::toVerifyTransactionData(
                $request->routePaymentTransaction(),
                $request->toDto(),
                $request->user(),
                $request->ip(),
            ),
        );

        return $this->respondResource(new PaymentTransactionResource($payment));
    }
}
