<?php

namespace App\Support\Http\Payments;

use App\DTOs\Payments\InitiatePaymentDTO;
use App\DTOs\Payments\VerifyPaymentDTO;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\Data\CreatePaymentInstructionsData;
use App\Services\Payments\Data\VerifyTransactionData;

class PaymentRequestMapper
{
    public static function toCreatePaymentInstructionsData(
        InitiatePaymentDTO $dto,
        ?User $actor,
        ?string $ipAddress,
    ): CreatePaymentInstructionsData {
        return new CreatePaymentInstructionsData(
            orderId: $dto->orderId,
            provider: $dto->provider,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }

    public static function toVerifyTransactionData(
        PaymentTransaction $payment,
        VerifyPaymentDTO $dto,
        ?User $actor,
        ?string $ipAddress,
    ): VerifyTransactionData {
        return new VerifyTransactionData(
            paymentTransactionId: (int) $payment->id,
            transactionNumber: $dto->transactionNumber,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }
}
