<?php

namespace App\Services\Payments;

use App\Domain\Correlation\Contracts\CorrelationContextInterface;
use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\DTOs\Payments\Gateway\InitiatePaymentResponse;
use App\DTOs\Payments\Gateway\RefundResponse;
use App\DTOs\Payments\Gateway\WebhookPayload;
use App\DTOs\Webhooks\IncomingWebhookData;
use App\DTOs\Webhooks\WebhookHandleResult;
use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\InfrastructureDomain\WebhookLogStatus;
use App\Enums\Payments\GatewayOutcome;
use App\Enums\Webhooks\WebhookEventType;
use App\Exceptions\Payments\Gateway\GatewayOperationException;
use App\Exceptions\Payments\Gateway\UnknownPaymentProviderException;
use App\Exceptions\Webhooks\UnsupportedWebhookEventException;
use App\Exceptions\Webhooks\WebhookVerificationException;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\WebhookLog;
use App\Services\Payments\Data\CompletePaymentData;
use App\Services\Payments\Data\FailPaymentData;
use App\Services\Payments\Data\GatewayInitiatePaymentData;
use App\Services\Payments\Data\GatewayInitiatePaymentResult;
use App\Services\Payments\Data\GatewayRefundData;
use App\Services\Payments\Data\InitiatePaymentData;
use App\Services\Payments\Gateway\PaymentGatewayRegistry;
use App\Services\Payments\Gateway\Support\GatewayProviderMetadata;
use App\Services\Payments\Mapping\InitiatePaymentRequestMapper;
use App\Services\Payments\Mapping\InitiatePaymentResponseMapper;
use App\Services\Payments\Mapping\RefundRequestMapper;
use App\Services\Refunds\Data\ProcessRefundData;
use App\Services\Refunds\Data\SubmitRefundData;
use App\Services\Refunds\RefundService;
use App\Services\TransactionRunner;
use App\Services\Webhooks\Data\WebhookDomainCommand;
use App\Services\Webhooks\ReplayProtectionService;
use App\Services\Webhooks\WebhookDomainCommandMapperRegistry;
use App\Services\Webhooks\WebhookLogService;
use App\Support\Payments\PaymentCorrelation;
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
        private InitiatePaymentRequestMapper $initiatePaymentRequestMapper,
        private InitiatePaymentResponseMapper $initiatePaymentResponseMapper,
        private RefundRequestMapper $refundRequestMapper,
    ) {}

    public function initiatePayment(GatewayInitiatePaymentData $data): GatewayInitiatePaymentResult
    {
        $order = Order::query()->whereKey($data->orderId)->firstOrFail();
        $provider = strtolower(trim($data->provider));

        $pending = PaymentTransaction::query()
            ->where('order_id', $order->id)
            ->where('provider', $provider)
            ->where('status', PaymentTransactionStatus::Pending)
            ->first();

        if ($pending !== null) {
            return new GatewayInitiatePaymentResult(
                payment: $pending,
                redirectUrl: $this->redirectUrlFromPayload($pending->payload),
            );
        }

        try {
            $gateway = $this->registry->paymentGateway($provider);
        } catch (UnknownPaymentProviderException $exception) {
            throw GatewayOperationException::forInitiate(
                provider: $provider,
                outcome: GatewayOutcome::ProviderError,
                reason: $exception->getMessage(),
            );
        }

        $amount = $this->resolveAmount($order, $data);
        $currency = $this->resolveCurrency($order, $data);

        $gatewayRequest = $this->initiatePaymentRequestMapper->toGatewayRequest(
            orderId: (int) $order->id,
            amount: $amount,
            currency: $currency,
            metadata: $data->metadata ?? [],
        );
        $gatewayResponse = $gateway->initiate($gatewayRequest);

        $this->assertSuccessfulInitiate($provider, $gatewayResponse);

        $domainData = $this->initiatePaymentResponseMapper->toDomainData(
            response: $gatewayResponse,
            orderId: (int) $order->id,
            provider: $provider,
            amount: $amount,
            currency: $currency,
            metadata: $data->metadata,
        );

        $domainData = new InitiatePaymentData(
            orderId: $domainData->orderId,
            provider: $domainData->provider,
            providerTransactionId: $domainData->providerTransactionId,
            amount: $domainData->amount,
            currency: $domainData->currency,
            payload: $domainData->payload,
            actor: $data->actor,
            ipAddress: $data->ipAddress,
        );

        $this->correlationContext->bind(
            PaymentCorrelation::forProviderTransaction($provider, $gatewayResponse->providerTransactionId),
        );

        try {
            $payment = $this->paymentService->initiatePayment($domainData);
        } finally {
            $this->correlationContext->clear();
        }

        return new GatewayInitiatePaymentResult(
            payment: $payment,
            redirectUrl: $gatewayResponse->redirectUrl,
        );
    }

    public function refund(GatewayRefundData $data): Refund
    {
        $refund = Refund::query()->whereKey($data->refundId)->firstOrFail();

        if ($refund->payment_transaction_id === null) {
            throw GatewayOperationException::forRefund(
                provider: 'unknown',
                outcome: GatewayOutcome::ProviderError,
                reason: "Refund {$refund->id} has no linked payment transaction",
            );
        }

        $payment = PaymentTransaction::query()
            ->whereKey($refund->payment_transaction_id)
            ->firstOrFail();

        $provider = strtolower(trim((string) $payment->provider));

        try {
            $gateway = $this->registry->refundGateway($provider);
        } catch (UnknownPaymentProviderException $exception) {
            throw GatewayOperationException::forRefund(
                provider: $provider,
                outcome: GatewayOutcome::ProviderError,
                reason: $exception->getMessage(),
            );
        }

        $gatewayRequest = $this->refundRequestMapper->toGatewayRequest(
            providerTransactionId: (string) $payment->provider_transaction_id,
            amount: number_format((float) $refund->amount, 2, '.', ''),
            currency: strtoupper((string) ($payment->currency ?: 'USD')),
            reason: $refund->reason,
            providerRefundId: $refund->provider_refund_id,
            metadata: [
                'refund_id' => $refund->id,
                'order_id' => $refund->order_id,
            ],
        );
        $gatewayResponse = $gateway->refund($gatewayRequest);

        $this->assertSuccessfulRefund($provider, $gatewayResponse);

        $this->correlationContext->bind(
            PaymentCorrelation::forProviderTransaction($provider, $gatewayResponse->providerRefundId),
        );

        try {
            if ($this->isImmediateRefundCompletion($gatewayResponse->status)) {
                return $this->refundService->processRefund(new ProcessRefundData(
                    refundId: (int) $refund->id,
                    providerRefundId: $gatewayResponse->providerRefundId,
                    actor: $data->actor,
                    ipAddress: $data->ipAddress,
                ));
            }

            return $this->refundService->submitRefund(new SubmitRefundData(
                refundId: (int) $refund->id,
                providerRefundId: $gatewayResponse->providerRefundId,
                actor: $data->actor,
                ipAddress: $data->ipAddress,
            ));
        } finally {
            $this->correlationContext->clear();
        }
    }

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

    private function assertSuccessfulInitiate(string $provider, InitiatePaymentResponse $response): void
    {
        if ($response->outcome === GatewayOutcome::Success && $response->providerTransactionId !== '') {
            return;
        }

        throw GatewayOperationException::forInitiate(
            provider: $provider,
            outcome: $response->outcome,
            reason: $this->gatewayFailureReason($response->providerMetadata, 'Provider rejected payment initiation'),
        );
    }

    private function assertSuccessfulRefund(string $provider, RefundResponse $response): void
    {
        if ($response->outcome === GatewayOutcome::Success && $response->providerRefundId !== '') {
            return;
        }

        throw GatewayOperationException::forRefund(
            provider: $provider,
            outcome: $response->outcome,
            reason: $this->gatewayFailureReason($response->providerMetadata, 'Provider rejected refund request'),
        );
    }

    private function isImmediateRefundCompletion(string $status): bool
    {
        return in_array(strtolower($status), ['completed', 'processed', 'success'], true);
    }

    /**
     * @param  array<string, mixed>  $providerMetadata
     */
    private function gatewayFailureReason(array $providerMetadata, string $fallback): string
    {
        $raw = $providerMetadata[GatewayProviderMetadata::RAW] ?? [];

        if (! is_array($raw)) {
            return $fallback;
        }

        foreach (['error', 'message'] as $key) {
            if (isset($raw[$key]) && (string) $raw[$key] !== '') {
                return (string) $raw[$key];
            }
        }

        return $fallback;
    }

    private function resolveAmount(Order $order, GatewayInitiatePaymentData $data): string
    {
        return number_format((float) ($data->amount ?? $order->total), 2, '.', '');
    }

    private function resolveCurrency(Order $order, GatewayInitiatePaymentData $data): string
    {
        if ($data->currency !== null && $data->currency !== '') {
            return strtoupper($data->currency);
        }

        $currency = $order->getAttribute('currency');

        if (is_string($currency) && $currency !== '') {
            return strtoupper($currency);
        }

        return 'USD';
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function redirectUrlFromPayload(?array $payload): ?string
    {
        if ($payload === null || ! isset($payload['redirect_url'])) {
            return null;
        }

        $redirectUrl = (string) $payload['redirect_url'];

        return $redirectUrl !== '' ? $redirectUrl : null;
    }
}
