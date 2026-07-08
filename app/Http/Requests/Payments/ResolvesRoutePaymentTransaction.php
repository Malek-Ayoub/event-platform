<?php

namespace App\Http\Requests\Payments;

use App\Models\PaymentTransaction;

trait ResolvesRoutePaymentTransaction
{
    public function routePaymentTransaction(): ?PaymentTransaction
    {
        $payment = $this->route('paymentTransaction');

        if ($payment instanceof PaymentTransaction) {
            return $payment;
        }

        if (is_numeric($payment)) {
            return PaymentTransaction::query()->find((int) $payment);
        }

        return null;
    }
}
