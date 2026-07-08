<?php

namespace App\Http\Requests\Events;

use App\DTOs\BaseDTO;
use App\DTOs\Events\UpdateTicketTypeDTO;
use App\Http\Requests\Api\BaseApiRequest;

class UpdateTicketTypeRequest extends BaseApiRequest
{
    use ResolvesRouteTicketType;

    public function authorize(): bool
    {
        $ticketType = $this->routeTicketType();

        return $ticketType !== null && ($this->user()?->can('update', $ticketType) ?? false);
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return UpdateTicketTypeDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'name' => ['sometimes', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'sale_start' => ['sometimes', 'nullable', 'date'],
            'sale_end' => ['sometimes', 'nullable', 'date', 'after_or_equal:sale_start'],
            'benefits' => ['sometimes', 'nullable', 'array'],
            'benefits.*' => ['string', 'max:255'],
            'color' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
