<?php

namespace App\Services\Outbox;

use App\Contracts\Outbox\OutboxConsumer;

final class OutboxConsumerRegistry
{
    /** @var list<OutboxConsumer> */
    private array $consumers = [];

    public function register(OutboxConsumer $consumer): void
    {
        if ($consumer->consumerKey() === '') {
            throw new \InvalidArgumentException('Outbox consumer must define a non-empty consumerKey().');
        }

        $this->consumers[] = $consumer;
    }

    /**
     * @return list<OutboxConsumer>
     */
    public function consumersFor(string $eventType): array
    {
        return array_values(array_filter(
            $this->consumers,
            fn (OutboxConsumer $consumer): bool => $consumer->supports($eventType),
        ));
    }
}
