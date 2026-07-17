<?php

namespace App\Http\Requests\Orders;

use App\DTOs\BaseDTO;
use App\DTOs\Orders\CreatePublicOrderDTO;
use App\Enums\EventDomain\EventStatus;
use App\Http\Requests\Api\BaseApiRequest;

class CreatePublicOrderRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return CreatePublicOrderDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // customer_user_id and reservation_id are intentionally omitted — guests cannot set them.
            'event_id' => [
                'required',
                'integer',
                $this->tenantExists('events')->where('status', EventStatus::Published->value),
            ],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'string', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.ticket_type_id' => ['required', 'integer', $this->tenantExists('ticket_types')],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
