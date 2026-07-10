<?php

namespace App\Services\Notifications\Data;

readonly class RenderedNotificationTemplate
{
    public function __construct(
        public string $subject,
        public string $body,
        public string $slug,
    ) {}
}
