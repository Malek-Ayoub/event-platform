<?php

namespace App\Enums\FinancialDomain;

enum PaymentTransactionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
