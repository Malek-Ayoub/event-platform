<?php

namespace App\Http\Requests\Events;

use App\Http\Requests\Api\BaseApiRequest;

class ShowEventRequest extends BaseApiRequest
{
    use ResolvesRouteEvent;

    public function authorize(): bool
    {
        $event = $this->routeEvent();

        return $event !== null && ($this->user()?->can('view', $event) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
