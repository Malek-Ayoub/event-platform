<?php

namespace App\Services\Outbox;

use App\Contracts\Outbox\OutboxConsumer;
use App\Models\OutboxEvent;
use App\Repositories\ConsumerReceiptRepository;
use App\Repositories\OutboxRepository;
use App\Services\Outbox\Data\OutboxDispatchResult;
use App\Services\TransactionRunner;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Polls pending outbox rows and dispatches them to registered consumers.
 *
 * Processing happens outside domain transactions (Phase 8.1 / §57).
 * Per-consumer idempotency is enforced via consumer receipts (Phase 8.1.5).
 */
final class OutboxDispatcher
{
    public function __construct(
        private OutboxRepository $repository,
        private ConsumerReceiptRepository $receiptRepository,
        private OutboxConsumerRegistry $registry,
        private OutboxTenantScope $tenantScope,
        private TransactionRunner $transactionRunner,
    ) {}

    public function dispatchPending(?int $batchSize = null): OutboxDispatchResult
    {
        $batchSize ??= (int) config('outbox.batch_size', 50);

        $events = $this->repository->claimPendingBatch($batchSize);

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($events as $event) {
            $result = $this->processEvent($event);
            $sent += $result->sent;
            $failed += $result->failed;
            $skipped += $result->skipped;
        }

        return new OutboxDispatchResult(
            claimed: $events->count(),
            sent: $sent,
            failed: $failed,
            skipped: $skipped,
        );
    }

    private function processEvent(OutboxEvent $event): OutboxDispatchResult
    {
        $consumers = $this->registry->consumersFor($event->event_type);

        if ($consumers === []) {
            $this->repository->markSent($event);

            return new OutboxDispatchResult(skipped: 1);
        }

        try {
            $executedConsumers = 0;
            $skippedConsumers = 0;

            $this->tenantScope->runForEvent($event, function () use (
                $consumers,
                $event,
                &$executedConsumers,
                &$skippedConsumers,
            ): void {
                foreach ($consumers as $consumer) {
                    if ($this->receiptRepository->hasProcessed($event->id, $consumer->consumerKey())) {
                        $skippedConsumers++;

                        continue;
                    }

                    $this->transactionRunner->run(function () use ($consumer, $event): void {
                        $consumer->handle($event->fresh());
                        $this->receiptRepository->markProcessed($event->id, $consumer->consumerKey());
                    });

                    $executedConsumers++;
                }
            });

            if (! $this->allConsumersProcessed($event->fresh())) {
                throw new \RuntimeException('Outbox event consumers were not fully processed.');
            }

            $this->repository->markSent($event->fresh());

            if ($executedConsumers === 0 && $skippedConsumers > 0) {
                return new OutboxDispatchResult(skipped: 1);
            }

            return new OutboxDispatchResult(sent: 1);
        } catch (Throwable $exception) {
            return $this->handleFailure($event->fresh(), $exception);
        }
    }

    private function allConsumersProcessed(OutboxEvent $event): bool
    {
        foreach ($this->registry->consumersFor($event->event_type) as $consumer) {
            if (! $this->receiptRepository->hasProcessed($event->id, $consumer->consumerKey())) {
                return false;
            }
        }

        return true;
    }

    private function handleFailure(OutboxEvent $event, Throwable $exception): OutboxDispatchResult
    {
        Log::warning('Outbox event processing failed.', [
            'outbox_event_id' => $event->id,
            'event_type' => $event->event_type,
            'attempts' => $event->attempts + 1,
            'exception' => $exception->getMessage(),
        ]);

        if ($event->attempts + 1 >= $this->repository->maxAttempts()) {
            $this->repository->markFailed($event);

            return new OutboxDispatchResult(failed: 1);
        }

        $this->repository->scheduleRetry($event);

        return new OutboxDispatchResult(failed: 1);
    }
}
