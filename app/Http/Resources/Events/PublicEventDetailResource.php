<?php

namespace App\Http\Resources\Events;

use App\Http\Resources\ApiResource;
use App\Services\Events\Data\PublicEventCatalogItem;
use App\Services\Events\Data\PublicEventTicketTypeItem;
use Illuminate\Http\Request;

/** @mixin PublicEventCatalogItem */
class PublicEventDetailResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PublicEventCatalogItem $item */
        $item = $this->resource;
        $event = $item->event;

        return [
            'id' => $event->id,
            'slug' => $event->slug,
            'title' => $event->name,
            'description' => $event->description,
            'venue' => $event->relationLoaded('venue') ? $event->venue?->name : null,
            'image_url' => $event->banner_url,
            'starts_at' => $event->start_datetime?->toIso8601String(),
            'ends_at' => $event->end_datetime?->toIso8601String(),
            'starting_price' => $item->startingPriceAmount !== null
                ? [
                    'amount' => $item->startingPriceAmount,
                    'currency' => $item->currency,
                ]
                : null,
            'ticket_types' => array_map(
                static fn (PublicEventTicketTypeItem $ticketType): array => [
                    'id' => $ticketType->id,
                    'name' => $ticketType->name,
                    'price' => [
                        'amount' => $ticketType->price,
                        'currency' => $ticketType->currency,
                    ],
                    'remaining' => $ticketType->remaining,
                    'is_available' => $ticketType->isAvailable,
                    'benefits' => $ticketType->benefits,
                    'color' => $ticketType->color,
                ],
                $item->ticketTypes,
            ),
        ];
    }
}
