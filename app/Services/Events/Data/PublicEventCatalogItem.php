<?php

namespace App\Services\Events\Data;

use App\Models\Event;

/**
 * Read-model envelope for a published event in the public catalog response.
 *
 * Keeps catalog-only computed fields (starting price, currency, ticket types) off the Eloquent model.
 */
readonly class PublicEventCatalogItem
{
    /**
     * @param  list<PublicEventTicketTypeItem>  $ticketTypes
     */
    public function __construct(
        public Event $event,
        public ?string $startingPriceAmount,
        public string $currency,
        public array $ticketTypes = [],
    ) {}
}
