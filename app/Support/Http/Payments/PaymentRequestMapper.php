<?php

namespace App\Support\Http\Payments;

use App\DTOs\Payments\CompletePaymentDTO;
use App\DTOs\Payments\FailPaymentDTO;
use App\DTOs\Payments\InitiatePaymentDTO;
use App\DTOs\Payments\VerifyPaymentDTO;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payments\Data\CompletePaymentData;
use App\Services\Payments\Data\CreatePaymentInstructionsData;
use App\Services\Payments\Data\FailPaymentData;
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
