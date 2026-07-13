<?php

namespace App\Services\Dashboard\Queries\Concerns;

trait FormatsDashboardValues
{
    protected function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    protected function percentageRate(int $checkedIn, int $ticketsIssued): string
    {
        if ($ticketsIssued === 0) {
            return '0.00';
        }

        return bcdiv(bcmul((string) $checkedIn, '100', 4), (string) $ticketsIssued, 2);
    }
}
