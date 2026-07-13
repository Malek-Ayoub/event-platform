<?php

namespace App\Services\Reports\Queries\Concerns;

use App\Services\Settlements\Data\SettlementDateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

trait AppliesReportDateRange
{
    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    protected function applyDateRange(Builder|QueryBuilder $query, string $column, SettlementDateRange $range): void
    {
        if ($range->from !== null) {
            $query->where($column, '>=', $range->from);
        }

        if ($range->to !== null) {
            $query->where($column, '<=', $range->to);
        }
    }

    protected function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    protected function percentageRate(int|float $numerator, int|float $denominator): string
    {
        if ((float) $denominator === 0.0) {
            return '0.00';
        }

        return bcdiv(bcmul((string) $numerator, '100', 4), (string) $denominator, 2);
    }
}
