<?php

namespace Tests\Unit\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScheduledCommandsTest extends TestCase
{
    /**
     * @return Collection<int, Event>
     */
    private function scheduledEvents(): Collection
    {
        return collect(app(Schedule::class)->events());
    }

    private function findScheduledEvent(string $commandFragment): Event
    {
        $event = $this->scheduledEvents()->first(
            fn (Event $event): bool => is_string($event->command)
                && str_contains($event->command, $commandFragment),
        );

        $this->assertInstanceOf(
            Event::class,
            $event,
            "Expected scheduled command containing [{$commandFragment}].",
        );

        return $event;
    }

    #[Test]
    public function it_schedules_outbox_process_every_minute(): void
    {
        $event = $this->findScheduledEvent('outbox:process --once');

        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }

    #[Test]
    public function it_schedules_expire_stale_orders_every_five_minutes(): void
    {
        $event = $this->findScheduledEvent('orders:expire-stale');

        $this->assertSame('*/5 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }
}
