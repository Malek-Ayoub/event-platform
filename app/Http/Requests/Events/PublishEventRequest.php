<?php

namespace App\Http\Requests\Events;

use App\Http\Requests\Api\BaseApiRequest;

class PublishEventRequest extends BaseApiRequest
{
    use ResolvesRouteEvent;

    public function authorize(): bool
    {
        $event = $this->routeEvent();

        return $event !== null && ($this->user()?->can('update', $event) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
