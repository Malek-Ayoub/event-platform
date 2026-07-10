<?php

namespace Tests\Support\Notifications;

use App\Contracts\Notifications\EmailSenderInterface;

final class RecordingEmailSender implements EmailSenderInterface
{
    /** @var list<array{to: string, subject: string, body: string, context: array<string, mixed>}> */
    public array $sent = [];

    private ?\Throwable $nextException = null;

    public function throwOnNextSend(\Throwable $exception): void
    {
        $this->nextException = $exception;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function send(string $to, string $subject, string $body, array $context = []): void
    {
        if ($this->nextException !== null) {
            $exception = $this->nextException;
            $this->nextException = null;

            throw $exception;
        }

        $this->sent[] = compact('to', 'subject', 'body', 'context');
    }
}
