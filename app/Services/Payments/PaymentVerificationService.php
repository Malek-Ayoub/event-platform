<?php

namespace App\Services\Payments;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\Payments\VerificationFailureReason;
use App\Exceptions\Payments\DuplicateTransactionNumberException;
use App\Models\PaymentTransaction;
use App\Services\Payments\Data\BeginVerificationData;
use App\Services\Payments\Data\ExpirePaymentData;
use App\Services\Payments\Data\GatewayVerifyTransactionData;
use App\Services\Payments\Data\MarkPaidData;
use App\Services\Payments\Data\MarkVerificationFailedData;
use App\Services\Payments\Data\VerifyTransactionData;
use App\Services\Payments\Gateway\Support\GatewayProviderConfig;

/**
 * Manual Wallet Transfer — orchestrates verification without direct Gateway access
 * (IMPLEMENTATION_ROADMAP.md §7.9.3/§7.9.6/§7.9.6.1).
 */
final class PaymentVerificationService
{
    public function __construct(
        private PaymentService $paymentService,
        private PaymentGatewayService $paymentGatewayService,
    ) {}

    public function verify(VerifyTransactionData $data): PaymentTransaction
    {
        $payment = $this->paymentService->getPayment(
            PaymentTransaction::query()->whereKey($data->paymentTransactionId)->firstOrFail(),
        );

        if ($payment->status === PaymentTransactionStatus::Paid) {
            return $payment;
        }

        if ($payment->status === PaymentTransactionStatus::Failed
            || $payment->status === PaymentTransactionStatus::Expired) {
            return $payment;
        }

        if ($this->hasExpired($payment)) {
            return $this->expireIfAllowed($payment, $data);
        }

        if (! $this->canVerify($payment->status)) {
            return $payment;
        }

        $transactionNumber = trim($data->transactionNumber);

        if ($this->transactionNumberUsedByAnotherPayment($transactionNumber, (int) $payment->id)) {
            throw DuplicateTransactionNumberException::forTransactionNumber($transactionNumber);
        }

        $payment = $this->paymentService->beginVerification(new BeginVerificationData(
            paymentTransactionId: (int) $payment->id,
            transactionNumber: $transactionNumber,
            actor: $data->actor,
            ipAddress: $data->ipAddress,
        ));

        $config = GatewayProviderConfig::forProvider((string) $payment->provider);

        $result = $this->paymentGatewayService->verifyTransaction(new GatewayVerifyTransactionData(
            provider: (string) $payment->provider,
            transactionNumber: $transactionNumber,
            expectedAmount: number_format((float) $payment->amount, 2, '.', ''),
            expectedCurrency: (string) $payment->currency,
            merchantAccount: $config->merchantAccount,
        ));

        if ($result->matched && $result->providerTransactionId !== null) {
            return $this->paymentService->markPaid(new MarkPaidData(
                paymentTransactionId: (int) $payment->id,
                providerTransactionId: $result->providerTransactionId,
                actor: $data->actor,
                ipAddress: $data->ipAddress,
            ));
        }

        return $this->paymentService->markVerificationFailed(new MarkVerificationFailedData(
            paymentTransactionId: (int) $payment->id,
            reason: $result->reason ?? VerificationFailureReason::NotFound,
            actor: $data->actor,
            ipAddress: $data->ipAddress,
        ));
    }

    private function canVerify(PaymentTransactionStatus $status): bool
    {
        return in_array($status, [
            PaymentTransactionStatus::AwaitingTransfer,
            PaymentTransactionStatus::Verifying,
        ], true);
    }

    private function hasExpired(PaymentTransaction $payment): bool
    {
        return $payment->expires_at !== null && $payment->expires_at->isPast();
    }

    private function expireIfAllowed(PaymentTransaction $payment, VerifyTransactionData $data): PaymentTransaction
    {
        if ($payment->status === PaymentTransactionStatus::AwaitingTransfer) {
            return $this->paymentService->expirePayment(new ExpirePaymentData(
                paymentTransactionId: (int) $payment->id,
                actor: $data->actor,
                ipAddress: $data->ipAddress,
            ));
        }

        if ($payment->status === PaymentTransactionStatus::Verifying) {
            return $this->paymentService->expirePayment(new ExpirePaymentData(
                paymentTransactionId: (int) $payment->id,
                actor: $data->actor,
                ipAddress: $data->ipAddress,
            ));
        }

        return $payment;
    }

    private function transactionNumberUsedByAnotherPayment(string $transactionNumber, int $excludePaymentId): bool
    {
        return PaymentTransaction::query()
            ->withoutGlobalScopes()
            ->where('transaction_number', $transactionNumber)
            ->whereKeyNot($excludePaymentId)
            ->exists();
    }
}
