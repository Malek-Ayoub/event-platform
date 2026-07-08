<?php

namespace App\Support\Http\Payments;

use App\DTOs\Payments\CompletePaymentDTO;
use App\DTOs\Payments\FailPaymentDTO;
use App\DTOs\Payments\InitiatePaymentDTO;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\Data\CompletePaymentData;
use App\Services\Payments\Data\FailPaymentData;
use App\Services\Payments\Data\GatewayInitiatePaymentData;

class PaymentRequestMapper
{
    public static function toGatewayInitiatePaymentData(InitiatePaymentDTO $dto, ?User $actor, ?string $ipAddress): GatewayInitiatePaymentData
    {
        return new GatewayInitiatePaymentData(
            orderId: $dto->orderId,
            provider: $dto->provider,
            amount: $dto->amount,
            currency: $dto->currency,
            metadata: $dto->metadata,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }

    public static function toCompletePaymentData(
        PaymentTransaction $payment,
        CompletePaymentDTO $dto,
        ?User $actor,
        ?string $ipAddress,
    ): CompletePaymentData {
        return new CompletePaymentData(
            paymentTransactionId: $payment->id,
            paymentMethod: $dto->paymentMethod,
            paymentReference: $dto->paymentReference,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }

    public static function toFailPaymentData(
        PaymentTransaction $payment,
        FailPaymentDTO $dto,
        ?User $actor,
        ?string $ipAddress,
    ): FailPaymentData {
        return new FailPaymentData(
            paymentTransactionId: $payment->id,
            reason: $dto->reason,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }
}
