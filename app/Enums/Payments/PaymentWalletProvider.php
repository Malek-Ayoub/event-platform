<?php

namespace App\Enums\Payments;

enum PaymentWalletProvider: string
{
    case ShamCash = 'shamcash';
    case Syriatel = 'syriatel';
}
