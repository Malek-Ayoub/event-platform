<?php

namespace App\Http\Requests\TaxRates;

use App\Http\Requests\Api\BaseApiRequest;

class DeleteTaxRateRequest extends BaseApiRequest
{
    use ResolvesRouteTaxRate;

    public function authorize(): bool
    {
        $taxRate = $this->routeTaxRate();

        return $taxRate !== null && ($this->user()?->can('delete', $taxRate) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
