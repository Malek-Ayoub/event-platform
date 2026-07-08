<?php

namespace App\Http\Requests\TaxRates;

use App\Http\Requests\Api\PaginatedListRequest;
use App\Models\TaxRate;

class ListTaxRatesRequest extends PaginatedListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', TaxRate::class) ?? false;
    }
}
