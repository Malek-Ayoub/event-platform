<?php

namespace App\Contracts\Notifications;

/**
 * Transport adapter for outbound email (Phase 8.2.1 stub — no real SMTP yet).
 */
interface EmailSenderInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function send(string $to, string $subject, string $body, array $context = []): void;
}
