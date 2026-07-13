<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Commissions\RecordCommissionPaymentRequest;
use App\Http\Resources\Commissions\CommissionPaymentResource;
use App\Services\Commissions\CommissionPaymentService;
use App\Support\Http\Commissions\CommissionPaymentRequestMapper;
use Illuminate\Http\JsonResponse;

class CommissionPaymentController extends BaseApiController
{
    public function __construct(
        private readonly CommissionPaymentService $commissionPaymentService,
    ) {}

    public function store(RecordCommissionPaymentRequest $request): JsonResponse
    {
        $result = $this->commissionPaymentService->recordPayment(
            CommissionPaymentRequestMapper::toRecordCommissionPaymentData(
                $request->toDto(),
                $request->user(),
                $request->ip(),
            ),
        );

        $payment = $result->payment;
        $payment->setAttribute('outstanding_commission', $result->settlementEntry->balance_after);

        return $this->respondCreated(new CommissionPaymentResource($payment));
    }
}
