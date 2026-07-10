<?php

namespace Tests\Unit\Services\Notifications;

use App\Contracts\Notifications\NotificationChannel;
use App\Services\Notifications\Data\NotificationMessage;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationDispatcherTest extends TestCase
{
    #[Test]
    public function it_routes_messages_to_the_matching_channel(): void
    {
        $received = [];

        $registry = tap(new NotificationRegistry, function (NotificationRegistry $registry) use (&$received): void {
            $registry->register(new class($received) implements NotificationChannel
            {
                public function __construct(private array &$received) {}

                public function channelKey(): string
                {
                    return 'email';
                }

                public function send(NotificationMessage $message): void
                {
                    $this->received[] = $message;
                }
            });
        });

        $message = new NotificationMessage(
            channelKey: 'email',
            recipient: 'customer@example.com',
            templateSlug: 'order.paid',
            variables: ['customer_name' => 'Alex'],
            venueId: 1,
        );

        $this->app->forgetInstance(NotificationDispatcher::class);
        $this->app->instance(NotificationRegistry::class, $registry);
        app(NotificationDispatcher::class)->dispatch($message);

        $this->assertCount(1, $received);
        $this->assertSame('customer@example.com', $received[0]->recipient);
        $this->assertSame('order.paid', $received[0]->templateSlug);
    }
}
