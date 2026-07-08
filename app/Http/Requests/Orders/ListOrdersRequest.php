<?php

namespace App\Http\Requests\Orders;

use App\Enums\OrdersDomain\OrderStatus;
use App\Http\Requests\Api\PaginatedListRequest;
use App\Models\Order;
use Illuminate\Validation\Rule;

class ListOrdersRequest extends PaginatedListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Order::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'event_id' => ['sometimes', 'integer', $this->tenantExists('events')],
            'status' => ['sometimes', 'string', Rule::enum(OrderStatus::class)],
        ]);
    }

    public function eventId(): ?int
    {
        $value = $this->validated('event_id');

        return $value !== null ? (int) $value : null;
    }

    public function status(): ?OrderStatus
    {
        $value = $this->validated('status');

        return $value !== null ? OrderStatus::from((string) $value) : null;
    }
}
