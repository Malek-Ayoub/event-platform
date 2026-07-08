<?php

namespace App\Http\Requests\Events;

use App\DTOs\BaseDTO;
use App\DTOs\Events\CreateTicketTypeDTO;
use App\Http\Requests\Api\BaseApiRequest;
use App\Models\TicketType;

class CreateTicketTypeRequest extends BaseApiRequest
{
    protected function prepareForValidation(): void
    {
        $event = $this->route('event');

        if (is_object($event) && method_exists($event, 'getKey')) {
            $this->merge(['event_id' => $event->getKey()]);
        } elseif (is_numeric($event)) {
            $this->merge(['event_id' => (int) $event]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', TicketType::class) ?? false;
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return CreateTicketTypeDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', $this->tenantExists('events')],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1'],
            'sale_start' => ['nullable', 'date'],
            'sale_end' => ['nullable', 'date', 'after_or_equal:sale_start'],
            'benefits' => ['nullable', 'array'],
            'benefits.*' => ['string', 'max:255'],
            'color' => ['nullable', 'string', 'max:50'],
        ];
    }
}
