<?php

namespace App\Services\Tickets\Data;

final class TicketEmailDeliveryResult
{
    public function __construct(
        public readonly bool $wasSent,
    ) {}
}
