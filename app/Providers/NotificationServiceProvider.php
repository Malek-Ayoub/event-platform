<?php

namespace App\Providers;

use App\Contracts\Notifications\EmailSenderInterface;
use App\Services\Notifications\Channels\EmailChannel;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationRegistry;
use App\Services\Notifications\Templates\EmailTemplateRenderer;
use App\Services\Notifications\Transport\LogEmailSender;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EmailSenderInterface::class, LogEmailSender::class);
        $this->app->singleton(EmailTemplateRenderer::class);

        $this->app->singleton(NotificationRegistry::class, function ($app): NotificationRegistry {
            $registry = new NotificationRegistry;
            $registry->register($app->make(EmailChannel::class));

            return $registry;
        });

        $this->app->singleton(NotificationDispatcher::class);
    }
}
