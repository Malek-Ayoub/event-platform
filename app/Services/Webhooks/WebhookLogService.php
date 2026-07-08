<?php

namespace App\Services\Webhooks;

use App\Enums\InfrastructureDomain\WebhookLogStatus;
use App\Models\WebhookLog;
use App\Support\Webhooks\WebhookCorrelation;
use Illuminate\Database\QueryException;

final class WebhookLogService
{
    public function recordReceived(
        string $provider,
        string $providerEventId,
        string $payload,
        ?string $signature,
    ): WebhookLog {
        try {
            return WebhookLog::query()->create([
                'provider' => $provider,
                'provider_event_id' => $providerEventId,
                'correlation_id' => WebhookCorrelation::id($provider, $providerEventId),
                'payload' => $payload,
                'signature' => $signature,
                'status' => WebhookLogStatus::Received,
            ]);
        } catch (QueryException) {
            return WebhookLog::query()
                ->where('provider', $provider)
                ->where('provider_event_id', $providerEventId)
                ->firstOrFail();
        }
    }

    public function markVerified(WebhookLog $log): WebhookLog
    {
        return $this->transition($log, WebhookLogStatus::Verified);
    }

    public function markFailedSignature(WebhookLog $log, string $reason): WebhookLog
    {
        return $this->transition($log, WebhookLogStatus::FailedSignature, $reason);
    }

    public function markReplayed(WebhookLog $log, string $reason): WebhookLog
    {
        return $this->transition($log, WebhookLogStatus::Replayed, $reason);
    }

    public function markProcessing(WebhookLog $log): WebhookLog
    {
        return $this->transition($log, WebhookLogStatus::Processing);
    }

    public function markProcessed(WebhookLog $log): WebhookLog
    {
        $log->update([
            'status' => WebhookLogStatus::Processed,
            'processed_at' => now(),
            'error_message' => null,
        ]);

        return $log->fresh();
    }

    public function markFailed(WebhookLog $log, string $reason): WebhookLog
    {
        return $this->transition($log, WebhookLogStatus::Failed, $reason);
    }

    private function transition(WebhookLog $log, WebhookLogStatus $status, ?string $errorMessage = null): WebhookLog
    {
        $log->update([
            'status' => $status,
            'error_message' => $errorMessage,
        ]);

        return $log->fresh();
    }
}
