<?php

namespace App\Repositories;

use App\Models\OutboxConsumerReceipt;
use Illuminate\Support\Carbon;

final class ConsumerReceiptRepository
{
    public function hasProcessed(int $outboxEventId, string $consumerKey): bool
    {
        return OutboxConsumerReceipt::query()
            ->where('outbox_event_id', $outboxEventId)
            ->where('consumer_key', $consumerKey)
            ->exists();
    }

    public function markProcessed(int $outboxEventId, string $consumerKey): void
    {
        OutboxConsumerReceipt::query()->firstOrCreate(
            [
                'outbox_event_id' => $outboxEventId,
                'consumer_key' => $consumerKey,
            ],
            [
                'processed_at' => Carbon::now(),
                'created_at' => Carbon::now(),
            ],
        );
    }
}
