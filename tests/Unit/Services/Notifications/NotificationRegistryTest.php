<?php

namespace Tests\Unit\Services\Notifications;

use App\Contracts\Notifications\NotificationChannel;
use App\Exceptions\Notifications\UnknownNotificationChannelException;
use App\Services\Notifications\Data\NotificationMessage;
use App\Services\Notifications\NotificationRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationRegistryTest extends TestCase
{
    #[Test]
    public function it_registers_and_resolves_channels_by_key(): void
    {
        $channel = new class implements NotificationChannel
        {
            public function channelKey(): string
            {
                return 'email';
            }

            public function send(NotificationMessage $message): void {}
        };

        $registry = new NotificationRegistry;
        $registry->register($channel);

        $this->assertSame($channel, $registry->resolve('email'));
        $this->assertSame(['email'], $registry->registeredChannelKeys());
    }

    #[Test]
    public function it_registers_email_channel_from_service_provider(): void
    {
        $keys = app(NotificationRegistry::class)->registeredChannelKeys();

        $this->assertContains('email', $keys);
    }

    #[Test]
    public function it_throws_for_unknown_channel_keys(): void
    {
        $this->expectException(UnknownNotificationChannelException::class);

        (new NotificationRegistry)->resolve('sms');
    }
}
