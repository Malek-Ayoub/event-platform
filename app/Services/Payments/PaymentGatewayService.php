<?php

namespace App\Services\Payments;

use App\Domain\Correlation\Contracts\CorrelationContextInterface;
use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\DTOs\Payments\Gateway\WebhookPayload;
use App\DTOs\Webhooks\IncomingWebhookData;
use App\DTOs\Webhooks\WebhookHandleResult;
use App\Enums\InfrastructureDomain\WebhookLogStatus;
use App\Enums\Webhooks\WebhookEventType;
use App\Exceptions\Payments\Gateway\UnknownPaymentProviderException;
use App\Exceptions\Webhooks\UnsupportedWebhookEventException;
use App\Exceptions\Webhooks\WebhookVerificationException;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\WebhookLog;
use App\Services\Payments\Data\CompletePaymentData;
use App\Services\Payments\Data\FailPaymentData;
use App\Services\Payments\Gateway\PaymentGatewayRegistry;
use App\Services\Refunds\Data\ProcessRefundData;
use App\Services\Refunds\RefundService;
use App\Services\TransactionRunner;
use App\Services\Webhooks\Data\WebhookDomainCommand;
use App\Services\Webhooks\ReplayProtectionService;
use App\Services\Webhooks\WebhookDomainCommandMapperRegistry;
use App\Services\Webhooks\WebhookLogService;
use Throwable;

/**
 * Anti-Corruption Layer between external payment providers and domain services.
 */
final class PaymentGatewayService
{
    public function __construct(
        private TransactionRunner $transactionRunner,
        private TenantContextInterface $tenantContext,
        private PaymentGatewayRegistry $registry,
        private WebhookLogService $webhookLogService,
        private ReplayProtectionService $replayProtectionService,
        private WebhookDomainCommandMapperRegistry $webhookDomainCommandMapperRegistry,
        private CorrelationContextInterface $correlationContext,
        private PaymentService $paymentService,
        private RefundService $refundService,
    ) {}

    public function handleWebhook(IncomingWebhookData $data): WebhookHandleResult
    {
        $log = $this->webhookLogService->recordReceived(
            provider: $data->provider,
            providerEventId: $data->providerEventId,
            payload: $data->rawBody,
            signature: $data->signature,
        );

        if ($this->replayProtectionService->isDuplicateDelivery($log)) {
            $log = $this->webhookLogService->markReplayed(
                log: $log,
                reason: 'Duplicate webhook delivery',
            );

            return new WebhookHandleResult(
                status: $log->status,
                webhookLogId: (int) $log->id,
                duplicate: true,
            );
        }

        $gatewayPayload = $this->toGatewayPayload($data);

        try {
            $verifier = $this->registry->signatureVerifier($data->provider);
        } catch (UnknownPaymentProviderException $exception) {
            $this->webhookLogService->markFailedSignature($log, $exception->getMessage());

            throw WebhookVerificationException::forProvider($data->provider, $exception->getMessage());
        }

        $verification = $verifier->verify($gatewayPayload);

        if (! $verification->verified) {
            $this->webhookLogService->markFailedSignature(
                log: $log,
                reason: $verification->failureReason ?? 'Signature verification failed',
            );

            throw WebhookVerificationException::forProvider(
                provider: $data->provider,
                reason: $verification->failureReason ?? 'Signature verification failed',
            );
        }

        $log = $this->webhookLogService->markVerified($log);
        $logId = (int) $log->id;

        try {
            return $this->transactionRunner->run(function () use ($logId, $gatewayPayload): WebhookHandleResult {
                $log = WebhookLog::query()->whereKey($logId)->lockForUpdate()->firstOrFail();

                if ($this->replayProtectionService->isInFlightOrCompleted($log)) {
                    $log = $this->webhookLogService->markReplayed(
                        log: $log,
                        reason: 'Duplicate webhook delivery during processing',
                    );

                    return new WebhookHandleResult(
                        status: $log->status,
                        webhookLogId: (int) $log->id,
                        duplicate: true,
                    );
                }

                $log = $this->webhookLogService->markProcessing($log);

                $this->correlationContext->bind((string) $log->correlation_id);

                try {
                    $command = $this->webhookDomainCommandMapperRegistry->map($gatewayPayload);
                    $this->dispatchDomainCommand($command);
                } finally {
                    $this->correlationContext->clear();
                }

                $log = $this->webhookLogService->markProcessed($log);

                return new WebhookHandleResult(
                    status: WebhookLogStatus::Processed,
                    webhookLogId: (int) $log->id,
                );
            });
        } catch (Throwable $exception) {
            $freshLog = WebhookLog::query()->whereKey($logId)->first();

            if ($freshLog !== null && $freshLog->status === WebhookLogStatus::Processing) {
                $this->webhookLogService->markFailed($freshLog, $exception->getMessage());
            }

            throw $exception;
        }
    }

    private function toGatewayPayload(IncomingWebhookData $data): WebhookPayload
    {
        return new WebhookPayload(
            provider: $data->provider,
            providerEventId: $data->providerEventId,
            rawBody: $data->rawBody,
            headers: $data->headers,
            parsedPayload: $data->parsedPayload,
        );
    }

    private function dispatchDomainCommand(WebhookDomainCommand $command): void
    {
        match ($command->eventType) {
            WebhookEventType::PaymentCompleted => $this->handlePaymentCompleted($command),
            WebhookEventType::PaymentFailed => $this->handlePaymentFailed($command),
            WebhookEventType::RefundProcessed => $this->handleRefundProcessed($command),
        };
    }

    private function handlePaymentCompleted(WebhookDomainCommand $command): void
    {
        $payment = $this->resolvePaymentTransaction($command);

        $this->runInVenueTenant((int) $payment->venue_id, function () use ($payment, $command): void {
            $this->paymentService->completePayment(new CompletePaymentData(
                paymentTransactionId: (int) $payment->id,
                paymentMethod: $command->provider,
                paymentReference: (string) ($command->payload['provider_transaction_id'] ?? $payment->provider_transaction_id),
            ));
        });
    }

    private function handlePaymentFailed(WebhookDomainCommand $command): void
    {
        $payment = $this->resolvePaymentTransaction($command);

        $this->runInVenueTenant((int) $payment->venue_id, function () use ($payment, $command): void {
            $this->paymentService->failPayment(new FailPaymentData(
                paymentTransactionId: (int) $payment->id,
                reason: isset($command->payload['reason']) ? (string) $command->payload['reason'] : null,
            ));
        });
    }

    private function handleRefundProcessed(WebhookDomainCommand $command): void
    {
        $refundId = $command->payload['refund_id'] ?? null;

        if ($refundId === null) {
            throw UnsupportedWebhookEventException::forEventType('refund.processed missing refund_id');
        }

        $refund = Refund::query()
            ->withoutGlobalScopes()
            ->whereKey((int) $refundId)
            ->firstOrFail();

        $this->runInVenueTenant((int) $refund->venue_id, function () use ($refundId, $command): void {
            $this->refundService->processRefund(new ProcessRefundData(
                refundId: (int) $refundId,
                providerRefundId: isset($command->payload['provider_refund_id'])
                    ? (string) $command->payload['provider_refund_id']
                    : null,
            ));
        });
    }

    private function resolvePaymentTransaction(WebhookDomainCommand $command): PaymentTransaction
    {
        $query = PaymentTransaction::query()->withoutGlobalScopes();

        if (isset($command->payload['payment_transaction_id'])) {
            return $query->whereKey((int) $command->payload['payment_transaction_id'])->firstOrFail();
        }

        $providerTransactionId = (string) ($command->payload['provider_transaction_id'] ?? '');

        if ($providerTransactionId === '') {
            throw UnsupportedWebhookEventException::forEventType($command->eventType->value.' missing provider_transaction_id');
        }

        return $query
            ->where('provider', $command->provider)
            ->where('provider_transaction_id', $providerTransactionId)
            ->firstOrFail();
    }

    private function runInVenueTenant(int $venueId, callable $callback): mixed
    {
        $this->tenantContext->bind($venueId, 'webhook');

        try {
            return $callback();
        } finally {
            $this->tenantContext->clear();
        }
    }
}
