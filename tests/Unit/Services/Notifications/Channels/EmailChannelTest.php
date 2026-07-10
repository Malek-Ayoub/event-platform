<?php

namespace Tests\Unit\Services\Notifications\Channels;

use App\Contracts\Notifications\EmailSenderInterface;
use App\Services\Notifications\Channels\EmailChannel;
use App\Services\Notifications\Data\NotificationMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailChannelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_templates_and_delegates_to_the_email_sender(): void
    {
        $sent = [];

        $this->app->instance(EmailSenderInterface::class, new class($sent) implements EmailSenderInterface
        {
            public function __construct(private array &$sent) {}

            public function send(string $to, string $subject, string $body, array $context = []): void
            {
                $this->sent[] = compact('to', 'subject', 'body', 'context');
            }
        });

        app(EmailChannel::class)->send(new NotificationMessage(
            channelKey: 'email',
            recipient: 'buyer@example.com',
            templateSlug: 'refund.processed',
            variables: [
                'customer_name' => 'Alex',
                'order_number' => 'ORD-300',
                'refund_amount' => '50.00',
            ],
        ));

        $this->assertCount(1, $sent);
        $this->assertSame('buyer@example.com', $sent[0]['to']);
        $this->assertSame('Refund processed for order ORD-300', $sent[0]['subject']);
        $this->assertStringContainsString('Alex', $sent[0]['body']);
        $this->assertSame('email', $sent[0]['context']['channel']);
        $this->assertSame('refund.processed', $sent[0]['context']['template_slug']);
    }
}
