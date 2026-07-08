<?php

namespace App\Services\Webhooks;

use App\DTOs\Webhooks\IncomingWebhookData;
use App\DTOs\Webhooks\WebhookHandleResult;
use App\Services\Payments\PaymentGatewayService;
use Illuminate\Http\Request;

/** Thin webhook orchestrator — normalize HTTP and delegate to PaymentGatewayService. */
final class WebhookService
{
    public function __construct(
        private PaymentGatewayService $paymentGatewayService,
    ) {}

    public function receive(string $provider, Request $request): WebhookHandleResult
    {
        $rawBody = $request->getContent();
        $parsedPayload = json_decode($rawBody, true);
        $parsedPayload = is_array($parsedPayload) ? $parsedPayload : [];

        return $this->paymentGatewayService->handleWebhook(new IncomingWebhookData(
            provider: $provider,
            providerEventId: $this->extractProviderEventId($parsedPayload),
            rawBody: $rawBody,
            headers: $this->normalizeHeaders($request),
            parsedPayload: $parsedPayload,
            signature: $this->extractSignatureHeader($request),
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractProviderEventId(array $payload): string
    {
        foreach (['event_id', 'provider_event_id', 'id'] as $key) {
            if (! empty($payload[$key])) {
                return (string) $payload[$key];
            }
        }

        return 'unknown';
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeHeaders(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $key => $values) {
            $headers[$key] = $values;
        }

        return $headers;
    }

    private function extractSignatureHeader(Request $request): ?string
    {
        foreach ([
            'X-ShamCash-Signature',
            'X-Syriatel-Signature',
            'X-Signature',
            'X-Webhook-Signature',
        ] as $header) {
            if ($request->headers->has($header)) {
                return $request->header($header);
            }
        }

        return null;
    }
}
