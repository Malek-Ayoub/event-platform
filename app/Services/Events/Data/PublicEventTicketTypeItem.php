<?php

namespace App\Services\Events\Data;

/**
 * Public catalog projection of a single ticket type on an event detail page.
 */
readonly class PublicEventTicketTypeItem
{
    /**
     * @param  list<string>|null  $benefits
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $price,
        public string $currency,
        public int $remaining,
        public bool $isAvailable,
        public ?array $benefits,
        public ?string $color,
    ) {}
}
