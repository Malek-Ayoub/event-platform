<?php

namespace App\Services\Orders\Data;

use App\Models\Ticket;

final class IssuedTicketsResult
{
    /**
     * @param  list<Ticket>  $tickets
     */
    public function __construct(
        public readonly array $tickets,
        public readonly bool $newlyIssued,
    ) {}
}
