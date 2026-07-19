<?php

namespace App\Http\Requests\Venues;

use App\Models\Venue;

trait ResolvesRouteVenue
{
    public function routeVenue(): ?Venue
    {
        $venue = $this->route('venue');

        if ($venue instanceof Venue) {
            return $venue;
        }

        if (is_numeric($venue)) {
            return Venue::query()->find((int) $venue);
        }

        return null;
    }
}
