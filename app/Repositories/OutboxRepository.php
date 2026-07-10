<?php

namespace App\Repositories;

use App\Enums\InfrastructureDomain\OutboxEventStatus;
use App\Models\OutboxEvent;
use App\Models\Scopes\BelongsToVenueScope;
use App\Services\TransactionRunner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class OutboxRepository
{
    public function __construct(
        private TransactionRunner $transactionRunner,
    ) {}

    /**
     * Atomically claim a batch of pending events for processing.
     *
     * Uses row-level locking inside a transaction to prevent double-processing
     * across concurrent workers.
     *
     * @return Collection<int, OutboxEvent>
     */
    public function claimPendingBatch(int $limit): Collection
    {
        return $this->transactionRunner->run(function () use ($limit): Collection {
            $this->releaseStaleProcessing();

            $query = OutboxEvent::query()
                ->withoutGlobalScope(BelongsToVenueScope::class)
                ->where('status', OutboxEventStatus::Pending)
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate();

            /** @var Collection<int, OutboxEvent> $events */
            $events = $query->get()->filter(fn (OutboxEvent $event): bool => $this->isReadyForRetry($event));

            foreach ($events as $event) {
                $event->update(['status' => OutboxEventStatus::Processing]);
            }

            return $events->values();
        });
    }

    public function markSent(OutboxEvent $event): void
    {
        $event->update([
            'status' => OutboxEventStatus::Sent,
            'processed_at' => now(),
        ]);
    }

    public function markFailed(OutboxEvent $event): void
    {
        $event->update([
            'status' => OutboxEventStatus::Failed,
            'attempts' => $event->attempts + 1,
            'processed_at' => now(),
        ]);
    }

    public function scheduleRetry(OutboxEvent $event): void
    {
        $event->update([
            'status' => OutboxEventStatus::Pending,
            'attempts' => $event->attempts + 1,
            'processed_at' => null,
        ]);
    }

    public function releaseStaleProcessing(): int
    {
        $cutoff = Carbon::now()->subMinutes((int) config('outbox.stale_processing_minutes', 15));

        return OutboxEvent::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->where('status', OutboxEventStatus::Processing)
            ->where('updated_at', '<=', $cutoff)
            ->update(['status' => OutboxEventStatus::Pending]);
    }

    public function isReadyForRetry(OutboxEvent $event): bool
    {
        if ($event->attempts === 0) {
            return true;
        }

        $backoffs = config('outbox.retry_backoff_seconds', [30, 60, 120, 300, 600]);
        $index = min($event->attempts - 1, count($backoffs) - 1);
        $delaySeconds = (int) $backoffs[$index];

        return $event->updated_at->copy()->addSeconds($delaySeconds)->isPast();
    }

    public function maxAttempts(): int
    {
        return (int) config('outbox.max_attempts', 5);
    }
}
