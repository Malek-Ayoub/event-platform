<?php

namespace App\Http\Resources\Payments;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Http\Resources\ApiResource;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;

/**
 * Limited public projection of guest payment verification outcome.
 *
 * @mixin PaymentTransaction
 */
class PublicPaymentVerificationResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status;

        return [
            'status' => $status->value,
            'message' => $this->messageForStatus($status),
        ];
    }

    private function messageForStatus(PaymentTransactionStatus $status): string
    {
        return match ($status) {
            PaymentTransactionStatus::Paid => 'Payment confirmed.',
            PaymentTransactionStatus::Failed => 'Payment verification failed.',
            PaymentTransactionStatus::Verifying => 'Payment verification in progress.',
            PaymentTransactionStatus::AwaitingTransfer => 'Waiting for wallet transfer.',
            PaymentTransactionStatus::Expired => 'Payment instruction has expired.',
            PaymentTransactionStatus::Pending,
            PaymentTransactionStatus::Completed,
            PaymentTransactionStatus::Refunded => 'Payment status updated.',
        };
    }
}
