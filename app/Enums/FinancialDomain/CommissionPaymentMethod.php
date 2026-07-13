<?php

namespace App\Enums\FinancialDomain;

enum CommissionPaymentMethod: string
{
    case Cash = 'cash';
    case Shamcash = 'shamcash';
    case SyriatelCash = 'syriatel_cash';
    case BankTransfer = 'bank_transfer';
    case Other = 'other';
}
