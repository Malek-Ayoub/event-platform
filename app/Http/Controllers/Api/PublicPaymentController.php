<?php

namespace App\Http\Controllers\Api;

use App\DTOs\Payments\VerifyPaymentDTO;
use App\Http\Requests\Payments\RequestPublicPaymentInstructionsRequest;
use App\Http\Requests\Payments\SubmitPublicPaymentVerificationRequest;
use App\Http\Resources\Payments\PublicPaymentInstructionResource;
use App\Http\Resources\Payments\PublicPaymentVerificationResource;
use App\Services\Payments\Data\CreatePaymentInstructionsData;
use App\Services\Payments\PaymentAccountResolver;
use App\Services\Payments\PaymentInstructionService;
use App\Services\Payments\PaymentVerificationService;
use App\Support\Http\Payments\PaymentRequestMapper;
use Illuminate\Http\JsonResponse;

class PublicPaymentController extends BaseApiController
{
    /**
     * Verification gateway slug stored on PaymentTransaction.
     *
     * Distinct from PaymentAccount.provider (wallet brand: shamcash/syriatel).
     * ApiSyria is the only registered verification gateway for Manual Wallet Transfer.
     */
    private const VERIFICATION_PROVIDER = 'apisyria';

    public function __construct(
        private readonly PaymentInstructionService $paymentInstructionService,
        private readonly PaymentVerificationService $paymentVerificationService,
        private readonly PaymentAccountResolver $paymentAccountResolver,
    ) {}

    public function instructions(RequestPublicPaymentInstructionsRequest $request): JsonResponse
    {
        $order = $request->resolvedOrder();

        // Ensures a merchant wallet is configured; wallet brand lives on PaymentAccount.provider.
        $this->paymentAccountResolver->resolveForOrder($order);

        $instructions = $this->paymentInstructionService->createInstructions(
            new CreatePaymentInstructionsData(
                orderId: (int) $order->getKey(),
                provider: self::VERIFICATION_PROVIDER,
                actor: null,
                ipAddress: $request->ip(),
            ),
        );

        return $this->respondCreated(new PublicPaymentInstructionResource($instructions));
    }

    public function verify(SubmitPublicPaymentVerificationRequest $request): JsonResponse
    {
        /** @var VerifyPaymentDTO $dto */
        $dto = $request->toDto();

        $payment = $this->paymentVerificationService->verify(
            PaymentRequestMapper::toVerifyTransactionData(
                $request->resolvedPaymentTransaction(),
                $dto,
                null,
                $request->ip(),
            ),
        );

        return $this->respondResource(new PublicPaymentVerificationResource($payment));
    }
}
