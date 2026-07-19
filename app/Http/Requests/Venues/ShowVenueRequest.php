<?php

namespace App\Http\Requests\Venues;

use App\Http\Requests\Api\BaseApiRequest;

class ShowVenueRequest extends BaseApiRequest
{
    use ResolvesRouteVenue;

    public function authorize(): bool
    {
        $venue = $this->routeVenue();

        return $venue !== null && ($this->user()?->can('view', $venue) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
