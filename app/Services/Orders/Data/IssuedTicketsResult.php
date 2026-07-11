<?php

namespace App\Services\Orders\Data;

use App\Models\Ticket;

final class IssuedTicketsResult
{
    /**
     * @param  list<Ticket>  $tickets
     * @param  list<Ticket>  $newlyIssuedTickets
     */
    public function __construct(
        public readonly array $tickets,
        public readonly bool $newlyIssued,
        public readonly array $newlyIssuedTickets = [],
    ) {}
}
