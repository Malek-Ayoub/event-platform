<?php

namespace App\Http\Requests\Venues;

use App\Http\Requests\Api\BaseApiRequest;

/**
 * Accepts name and commission_rate only.
 * subdomain / owner fields are intentionally omitted from rules so they are
 * silently ignored if sent — changing subdomain would break tenant resolution.
 */
class UpdateVenueRequest extends BaseApiRequest
{
    use ResolvesRouteVenue;

    public function authorize(): bool
    {
        $venue = $this->routeVenue();

        return $venue !== null && ($this->user()?->can('update', $venue) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'commission_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
