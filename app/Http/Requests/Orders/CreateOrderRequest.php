<?php

namespace App\Http\Requests\Orders;

use App\DTOs\BaseDTO;
use App\DTOs\Orders\CreateOrderDTO;
use App\Http\Requests\Api\BaseApiRequest;
use App\Models\Order;

class CreateOrderRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return CreateOrderDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'string', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'reservation_id' => ['nullable', 'integer', 'exists:reservations,id'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.ticket_type_id' => ['required', 'integer', 'exists:ticket_types,id'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
