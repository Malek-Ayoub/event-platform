<?php

namespace App\Enums\FinancialDomain;

enum CommissionStatus: string
{
    case Pending = 'pending';
    case Invoiced = 'invoiced';
    case Paid = 'paid';
}
