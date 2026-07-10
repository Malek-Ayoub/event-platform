<?php

namespace App\Exceptions\Notifications;

use RuntimeException;

final class NotificationTemplateNotFoundException extends RuntimeException
{
    public static function forSlug(string $slug, ?int $venueId = null): self
    {
        $scope = $venueId === null ? 'platform' : "venue {$venueId}";

        return new self("No active notification template found for slug [{$slug}] in scope [{$scope}].");
    }
}
