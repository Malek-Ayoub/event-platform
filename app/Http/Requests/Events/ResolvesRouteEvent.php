<?php

namespace App\Http\Requests\Events;

use App\Models\Event;

trait ResolvesRouteEvent
{
    public function routeEvent(): ?Event
    {
        $event = $this->route('event');

        if ($event instanceof Event) {
            return $event;
        }

        if (is_numeric($event)) {
            return Event::query()->find((int) $event);
        }

        return null;
    }
}
