<?php

namespace App\Http\Requests\TaxRates;

use App\DTOs\BaseDTO;
use App\DTOs\TaxRates\UpdateTaxRateDTO;
use App\Http\Requests\Api\BaseApiRequest;

class UpdateTaxRateRequest extends BaseApiRequest
{
    use ResolvesRouteTaxRate;

    public function authorize(): bool
    {
        $taxRate = $this->routeTaxRate();

        return $taxRate !== null && ($this->user()?->can('update', $taxRate) ?? false);
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return UpdateTaxRateDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'name' => ['sometimes', 'string', 'max:255'],
            'rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
