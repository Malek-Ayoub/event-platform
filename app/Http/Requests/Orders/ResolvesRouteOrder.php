<?php

namespace App\Http\Requests\Orders;

use App\Models\Order;

trait ResolvesRouteOrder
{
    public function routeOrder(): ?Order
    {
        $order = $this->route('order');

        if ($order instanceof Order) {
            return $order;
        }

        if (is_numeric($order)) {
            return Order::query()->find((int) $order);
        }

        return null;
    }
}
