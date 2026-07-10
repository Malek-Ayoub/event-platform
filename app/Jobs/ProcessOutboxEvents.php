<?php

namespace App\Jobs;

use App\Services\Outbox\OutboxDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProcessOutboxEvents implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?int $batchSize = null,
    ) {
        $this->onQueue(config('platform.queues.outbox', 'outbox'));
    }

    public function handle(OutboxDispatcher $dispatcher): void
    {
        $dispatcher->dispatchPending($this->batchSize);
    }
}
