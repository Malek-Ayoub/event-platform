<?php

namespace App\Services\Payments;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Payments\Data\CreateAwaitingTransferData;
use App\Services\Payments\Data\CreatePaymentInstructionsData;
use App\Services\Payments\Data\ExpirePaymentData;
use App\Services\Payments\Data\PaymentInstructionData;
use App\Services\Payments\Gateway\Support\GatewayProviderConfig;
use Illuminate\Support\Carbon;

/**
 * Manual Wallet Transfer — issues payment instructions without any Gateway calls
 * (IMPLEMENTATION_ROADMAP.md §7.9.3).
 */
final class PaymentInstructionService
{
    public function __construct(
        private PaymentService $paymentService,
    ) {}

    public function createInstructions(CreatePaymentInstructionsData $data): PaymentInstructionData
    {
        $provider = strtolower(trim($data->provider));
        $config = GatewayProviderConfig::forProvider($provider);

        $existing = PaymentTransaction::query()
            ->where('order_id', $data->orderId)
            ->where('provider', $provider)
            ->where('status', PaymentTransactionStatus::AwaitingTransfer)
            ->first();

        if ($existing !== null && $this->isActiveInstruction($existing)) {
            return $this->toInstructionData($existing, $config->merchantAccount);
        }

        if ($existing !== null) {
            $this->paymentService->expirePayment(new ExpirePaymentData(
                paymentTransactionId: (int) $existing->id,
                actor: $data->actor,
                ipAddress: $data->ipAddress,
            ));
        }

        $order = Order::query()->whereKey($data->orderId)->firstOrFail();
        $expiresAt = now()->addHours((int) config('payment_gateways.instruction_ttl_hours', 24));

        $payment = $this->paymentService->createAwaitingTransfer(new CreateAwaitingTransferData(
            orderId: (int) $order->id,
            provider: $provider,
            amount: number_format((float) $order->total, 2, '.', ''),
            currency: $this->resolveCurrency($order),
            expiresAt: $expiresAt,
            actor: $data->actor,
            ipAddress: $data->ipAddress,
        ));

        return $this->toInstructionData($payment, $config->merchantAccount);
    }

    private function isActiveInstruction(PaymentTransaction $payment): bool
    {
        return $payment->expires_at === null || $payment->expires_at->isFuture();
    }

    private function toInstructionData(PaymentTransaction $payment, string $merchantAccount): PaymentInstructionData
    {
        $expiresAt = $payment->expires_at ?? now()->addHours((int) config('payment_gateways.instruction_ttl_hours', 24));

        return new PaymentInstructionData(
            paymentId: (int) $payment->id,
            provider: (string) $payment->provider,
            merchantAccount: $merchantAccount,
            amount: number_format((float) $payment->amount, 2, '.', ''),
            currency: (string) $payment->currency,
            expiresAt: Carbon::parse($expiresAt),
            instructions: $this->buildInstructions(
                merchantAccount: $merchantAccount,
                amount: number_format((float) $payment->amount, 2, '.', ''),
                currency: (string) $payment->currency,
                expiresAt: Carbon::parse($expiresAt),
            ),
        );
    }

    private function buildInstructions(
        string $merchantAccount,
        string $amount,
        string $currency,
        Carbon $expiresAt,
    ): string {
        return sprintf(
            'Transfer exactly %s %s to merchant wallet %s before %s, then submit your transaction number to complete payment.',
            $amount,
            $currency,
            $merchantAccount,
            $expiresAt->toIso8601String(),
        );
    }

    private function resolveCurrency(Order $order): string
    {
        $currency = $order->getAttribute('currency');

        if (is_string($currency) && $currency !== '') {
            return strtoupper($currency);
        }

        return 'USD';
    }
}
