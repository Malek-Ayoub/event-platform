<?php

namespace App\Http\Requests\Commerce;

use App\Enums\CommerceDomain\DiscountType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesDiscountFields
{
    /**
     * @return array<string, mixed>
     */
    protected function discountFieldRules(bool $required = true): array
    {
        $sometimes = $required ? 'required' : 'sometimes';

        return [
            'discount_type' => [$sometimes, Rule::enum(DiscountType::class)],
            'discount_value' => [$sometimes, 'numeric', 'min:0'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('discount_type');
            $value = $this->input('discount_value');

            if ($type === DiscountType::Percent->value && $value !== null && (float) $value > 100) {
                $validator->errors()->add('discount_value', 'The discount value must not exceed 100 for percent discounts.');
            }
        });
    }
}
