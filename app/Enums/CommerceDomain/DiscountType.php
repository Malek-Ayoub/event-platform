<?php

namespace App\Enums\CommerceDomain;

enum DiscountType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
}
