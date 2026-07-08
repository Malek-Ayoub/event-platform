<?php

namespace App\Http\Requests\Commerce;

use App\Http\Requests\Api\PaginatedListRequest;
use App\Models\PromoCode;

class ListPromoCodesRequest extends PaginatedListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PromoCode::class) ?? false;
    }
}
