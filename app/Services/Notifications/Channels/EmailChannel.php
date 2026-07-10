<?php

namespace App\Services\Notifications\Channels;

use App\Contracts\Notifications\EmailSenderInterface;
use App\Contracts\Notifications\NotificationChannel;
use App\Services\Notifications\Data\NotificationMessage;
use App\Services\Notifications\Templates\EmailTemplateRenderer;

final class EmailChannel implements NotificationChannel
{
    public function __construct(
        private EmailTemplateRenderer $templateRenderer,
        private EmailSenderInterface $emailSender,
    ) {}

    public function channelKey(): string
    {
        return 'email';
    }

    public function send(NotificationMessage $message): void
    {
        $rendered = $this->templateRenderer->render(
            slug: $message->templateSlug,
            variables: $message->variables,
            venueId: $message->venueId,
        );

        $this->emailSender->send(
            to: $message->recipient,
            subject: $rendered->subject,
            body: $rendered->body,
            context: [
                'channel' => $this->channelKey(),
                'template_slug' => $rendered->slug,
                'venue_id' => $message->venueId,
            ],
        );
    }
}
