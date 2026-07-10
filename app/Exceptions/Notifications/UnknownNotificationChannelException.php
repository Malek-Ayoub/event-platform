<?php

namespace App\Exceptions\Notifications;

use RuntimeException;

final class UnknownNotificationChannelException extends RuntimeException
{
    public static function forChannel(string $channelKey): self
    {
        return new self("No notification channel registered for key [{$channelKey}].");
    }
}
