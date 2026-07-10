<?php

namespace App\Console\Commands;

use App\Services\Outbox\Data\OutboxDispatchResult;
use App\Services\Outbox\OutboxDispatcher;
use Illuminate\Console\Command;

final class OutboxProcessCommand extends Command
{
    protected $signature = 'outbox:process
                            {--once : Process a single batch and exit}
                            {--batch= : Override configured batch size}';

    protected $description = 'Process pending outbox events via registered consumers';

    public function handle(OutboxDispatcher $dispatcher): int
    {
        $batchSize = $this->option('batch') !== null
            ? (int) $this->option('batch')
            : null;

        if ($this->option('once')) {
            $this->reportResult($dispatcher->dispatchPending($batchSize));

            return self::SUCCESS;
        }

        do {
            $result = $dispatcher->dispatchPending($batchSize);
            $this->reportResult($result);
        } while ($result->claimed > 0);

        return self::SUCCESS;
    }

    private function reportResult(OutboxDispatchResult $result): void
    {
        $this->line(sprintf(
            'Outbox batch: claimed=%d sent=%d failed=%d skipped=%d',
            $result->claimed,
            $result->sent,
            $result->failed,
            $result->skipped,
        ));
    }
}
