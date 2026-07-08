<?php

namespace App\Services\Payments\Gateway\SyriatelCash;

use App\Services\Payments\Gateway\Support\HmacWebhookSignatureVerifier;

final class SyriatelCashSignatureVerifier extends HmacWebhookSignatureVerifier
{
    public function provider(): string
    {
        return 'syriatel_cash';
    }
}
