<?php

namespace App\Http\Requests\TaxRates;

use App\Models\TaxRate;

trait ResolvesRouteTaxRate
{
    public function routeTaxRate(): ?TaxRate
    {
        $taxRate = $this->route('taxRate');

        if ($taxRate instanceof TaxRate) {
            return $taxRate;
        }

        if (is_numeric($taxRate)) {
            return TaxRate::query()->find((int) $taxRate);
        }

        return null;
    }
}
