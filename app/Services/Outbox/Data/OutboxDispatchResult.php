<?php

namespace App\Services\Outbox\Data;

readonly class OutboxDispatchResult
{
    public function __construct(
        public int $claimed = 0,
        public int $sent = 0,
        public int $failed = 0,
        public int $skipped = 0,
    ) {}

    public function merge(self $other): self
    {
        return new self(
            claimed: $this->claimed + $other->claimed,
            sent: $this->sent + $other->sent,
            failed: $this->failed + $other->failed,
            skipped: $this->skipped + $other->skipped,
        );
    }
}
