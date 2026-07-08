<?php

namespace App\Enums\InfrastructureDomain;

enum WebhookLogStatus: string
{
    case Received = 'received';
    case Verified = 'verified';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
    case FailedSignature = 'failed_signature';
    case Replayed = 'replayed';

    /**
     * @return list<self>
     */
    public static function terminalStatuses(): array
    {
        return [
            self::Processed,
            self::Failed,
            self::FailedSignature,
            self::Replayed,
        ];
    }

    /**
     * @return list<self>
     */
    public static function replayableDuplicateStatuses(): array
    {
        return [
            self::Verified,
            self::Processing,
            self::Processed,
            self::Failed,
            self::Replayed,
        ];
    }
}
