<?php

namespace App\Http\Requests\TaxRates;

use App\DTOs\BaseDTO;
use App\DTOs\TaxRates\CreateTaxRateDTO;
use App\Http\Requests\Api\BaseApiRequest;
use App\Models\TaxRate;

class CreateTaxRateRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TaxRate::class) ?? false;
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return CreateTaxRateDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
