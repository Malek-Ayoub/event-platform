<?php

namespace App\Http\Requests\Orders;

use App\Http\Requests\Api\BaseApiRequest;

class ShowOrderRequest extends BaseApiRequest
{
    use ResolvesRouteOrder;

    public function authorize(): bool
    {
        $order = $this->routeOrder();

        return $order !== null && ($this->user()?->can('view', $order) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
