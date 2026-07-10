<?php

namespace App\Services\Notifications\Data;

readonly class NotificationMessage
{
    /**
     * @param  array<string, scalar|null>  $variables
     */
    public function __construct(
        public string $channelKey,
        public string $recipient,
        public string $templateSlug,
        public array $variables = [],
        public ?int $venueId = null,
    ) {}
}
