<?php

namespace App\Services\Payments\Mapping;

use App\DTOs\Payments\Gateway\GatewayPaymentAccount;
use App\Models\PaymentAccount;

final class GatewayPaymentAccountMapper
{
    public function toGatewayAccount(PaymentAccount $account): GatewayPaymentAccount
    {
        return new GatewayPaymentAccount(
            provider: $account->provider,
            accountIdentifier: $account->account_identifier,
            cashCode: $account->cash_code,
            currency: $account->currency,
            displayName: $account->display_name,
        );
    }
}
