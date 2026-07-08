<?php

namespace App\Services\Payments\Gateway\ShamCash;

use App\Services\Payments\Gateway\Support\HmacWebhookSignatureVerifier;

final class ShamCashSignatureVerifier extends HmacWebhookSignatureVerifier
{
    public function provider(): string
    {
        return 'shamcash';
    }
}
