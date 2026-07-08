<?php

namespace App\Http\Requests\Commerce;

use App\Http\Requests\Api\PaginatedListRequest;
use App\Models\Coupon;

class ListCouponsRequest extends PaginatedListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Coupon::class) ?? false;
    }
}
