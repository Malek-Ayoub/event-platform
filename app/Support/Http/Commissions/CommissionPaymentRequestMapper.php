<?php

namespace App\Support\Http\Commissions;

use App\DTOs\Commissions\RecordCommissionPaymentDTO;
use App\Models\User;
use App\Services\Commissions\Data\RecordCommissionPaymentData;
use Illuminate\Support\Carbon;

final class CommissionPaymentRequestMapper
{
    public static function toRecordCommissionPaymentData(
        RecordCommissionPaymentDTO $dto,
        User $receivedBy,
        ?string $ipAddress,
    ): RecordCommissionPaymentData {
        return new RecordCommissionPaymentData(
            venueId: $dto->venueId,
            amount: $dto->amount,
            currency: $dto->currency,
            paymentMethod: $dto->paymentMethod,
            receivedAt: Carbon::parse($dto->receivedAt),
            receivedBy: $receivedBy,
            paymentAccountId: $dto->paymentAccountId,
            referenceNumber: $dto->referenceNumber,
            notes: $dto->notes,
            ipAddress: $ipAddress,
        );
    }
}
