<?php

namespace App\Http\Requests\Events;

use App\Http\Requests\Api\BaseApiRequest;

class DeleteTicketTypeRequest extends BaseApiRequest
{
    use ResolvesRouteTicketType;

    public function authorize(): bool
    {
        $ticketType = $this->routeTicketType();

        return $ticketType !== null && ($this->user()?->can('delete', $ticketType) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
