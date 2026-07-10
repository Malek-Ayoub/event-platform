<?php

namespace App\Services\Payments\Gateway\ApiSyria;

use App\DTOs\Payments\Gateway\GatewayPaymentAccount;
use App\Enums\Payments\PaymentWalletProvider;

/**
 * Maps raw API Syria JSON payloads to normalized verification fields.
 */
final class ApiSyriaResponseParser
{
    /**
     * @param  array<string, mixed>  $body
     * @return array{
     *     found: bool,
     *     amount: ?string,
     *     currency: ?string,
     *     receiverAccount: ?string,
     *     providerTransactionId: ?string,
     *     rawStatus: ?string,
     *     raw: array<string, mixed>
     * }
     */
    public function parseFindTxResponse(array $body, GatewayPaymentAccount $paymentAccount): array
    {
        /** @var array<string, mixed> $data */
        $data = is_array($body['data'] ?? null) ? $body['data'] : $body;
        $found = (bool) ($data['found'] ?? false);

        if (! $found) {
            return [
                'found' => false,
                'amount' => null,
                'currency' => null,
                'receiverAccount' => null,
                'providerTransactionId' => null,
                'rawStatus' => null,
                'raw' => $data,
            ];
        }

        /** @var array<string, mixed> $transaction */
        $transaction = is_array($data['transaction'] ?? null) ? $data['transaction'] : [];

        if ($paymentAccount->provider === PaymentWalletProvider::Syriatel) {
            return [
                'found' => true,
                'amount' => isset($transaction['amount']) ? (string) $transaction['amount'] : null,
                'currency' => $paymentAccount->currency ?? 'SYP',
                'receiverAccount' => isset($transaction['to']) ? (string) $transaction['to'] : $paymentAccount->accountIdentifier,
                'providerTransactionId' => isset($transaction['transaction_no'])
                    ? (string) $transaction['transaction_no']
                    : null,
                'rawStatus' => isset($transaction['date']) ? (string) $transaction['date'] : null,
                'raw' => $data,
            ];
        }

        return [
            'found' => true,
            'amount' => isset($transaction['amount']) ? (string) $transaction['amount'] : null,
            'currency' => isset($transaction['currency'])
                ? (string) $transaction['currency']
                : $paymentAccount->currency,
            'receiverAccount' => isset($transaction['account'])
                ? (string) $transaction['account']
                : $paymentAccount->accountIdentifier,
            'providerTransactionId' => isset($transaction['tran_id'])
                ? (string) $transaction['tran_id']
                : null,
            'rawStatus' => isset($transaction['datetime']) ? (string) $transaction['datetime'] : null,
            'raw' => $data,
        ];
    }
}
