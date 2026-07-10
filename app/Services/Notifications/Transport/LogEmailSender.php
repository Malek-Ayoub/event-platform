<?php

namespace App\Services\Notifications\Transport;

use App\Contracts\Notifications\EmailSenderInterface;
use Illuminate\Support\Facades\Log;

/**
 * Phase 8.2.1 — records outbound email intent without sending real mail.
 */
final class LogEmailSender implements EmailSenderInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function send(string $to, string $subject, string $body, array $context = []): void
    {
        Log::info('notification.email.dispatched', [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'context' => $context,
        ]);
    }
}
