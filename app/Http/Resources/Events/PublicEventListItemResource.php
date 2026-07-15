<?php

namespace App\Http\Resources\Events;

use App\Http\Resources\ApiResource;
use App\Services\Events\Data\PublicEventCatalogItem;
use Illuminate\Http\Request;

/** @mixin PublicEventCatalogItem */
class PublicEventListItemResource extends ApiResource
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
            'starting_price' => $item->startingPriceAmount !== null
                ? [
                    'amount' => $item->startingPriceAmount,
                    'currency' => $item->currency,
                ]
                : null,
        ];
    }
}
