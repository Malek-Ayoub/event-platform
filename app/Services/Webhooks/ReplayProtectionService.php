<?php

namespace App\Services\Webhooks;

use App\Enums\InfrastructureDomain\WebhookLogStatus;
use App\Models\WebhookLog;

final class ReplayProtectionService
{
    public function isDuplicateDelivery(WebhookLog $log): bool
    {
        return in_array($log->status, [
            WebhookLogStatus::Processing,
            WebhookLogStatus::Processed,
            WebhookLogStatus::Failed,
            WebhookLogStatus::FailedSignature,
            WebhookLogStatus::Replayed,
        ], true);
    }

    public function isInFlightOrCompleted(WebhookLog $log): bool
    {
        return in_array($log->status, [
            WebhookLogStatus::Processing,
            WebhookLogStatus::Processed,
            WebhookLogStatus::Replayed,
        ], true);
    }

    public function isTerminal(WebhookLog $log): bool
    {
        return in_array($log->status, WebhookLogStatus::terminalStatuses(), true);
    }
}
