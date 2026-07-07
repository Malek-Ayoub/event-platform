<?php

namespace Tests\Unit\Services;

use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\Scopes\BelongsToVenueScope;
use App\Services\ActivityLogService;
use App\Services\OutboxService;
use App\Services\TransactionRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class InfrastructureFoundationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function activity_log_and_outbox_commit_together_inside_transaction_runner(): void
    {
        ['venue' => $venue, 'user' => $actor] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();

        $runner = app(TransactionRunner::class);
        $activityLogService = app(ActivityLogService::class);
        $outboxService = app(OutboxService::class);

        $runner->run(function () use ($activityLogService, $outboxService, $actor, $order): void {
            $activityLogService->record($actor, $order, 'paid');
            $outboxService->record('order.paid', $order, ['order_id' => $order->id]);
        });

        $this->assertCount(1, ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('venue_id', $venue->id)->get());
        $this->assertCount(1, OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('venue_id', $venue->id)->get());
    }

    #[Test]
    public function activity_log_and_outbox_roll_back_together_when_transaction_fails(): void
    {
        ['venue' => $venue, 'user' => $actor] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();

        $runner = app(TransactionRunner::class);
        $activityLogService = app(ActivityLogService::class);
        $outboxService = app(OutboxService::class);

        try {
            $runner->run(function () use ($activityLogService, $outboxService, $actor, $order): void {
                $activityLogService->record($actor, $order, 'paid');
                $outboxService->record('order.paid', $order, ['order_id' => $order->id]);

                throw new RuntimeException('fail');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertCount(0, ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('venue_id', $venue->id)->get());
        $this->assertCount(0, OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('venue_id', $venue->id)->get());
    }
}
