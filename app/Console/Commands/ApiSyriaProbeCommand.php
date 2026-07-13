<?php

namespace App\Console\Commands;

use App\Models\PaymentAccount;
use App\Services\Payments\Gateway\ApiSyria\ApiSyriaProbeService;
use App\Services\Payments\Gateway\Support\GatewayProviderConfig;
use App\Services\Payments\Mapping\GatewayPaymentAccountMapper;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

/**
 * Phase 7.10 — manual live integration checks against API Syria read endpoints.
 */
final class ApiSyriaProbeCommand extends Command
{
    protected $signature = 'apisyria:probe
                            {action : status|accounts|find-tx}
                            {tx? : Transaction number for find-tx}
                            {--payment-account= : Payment account ID from payment_accounts table}';

    protected $description = 'Probe API Syria read endpoints (status, accounts, find_tx)';

    public function handle(
        ApiSyriaProbeService $probe,
        GatewayPaymentAccountMapper $paymentAccountMapper,
    ): int {
        try {
            $this->assertConfigured();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $action = strtolower(trim((string) $this->argument('action')));

        try {
            $payload = match ($action) {
                'status' => $probe->status(),
                'accounts' => $probe->listAccounts(),
                'find-tx' => $this->findTransaction($probe, $paymentAccountMapper),
                default => throw new InvalidArgumentException("Unknown action [{$action}]. Use status, accounts, or find-tx."),
            };
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function findTransaction(
        ApiSyriaProbeService $probe,
        GatewayPaymentAccountMapper $paymentAccountMapper,
    ): array {
        $transactionNumber = trim((string) ($this->argument('tx') ?? ''));

        if ($transactionNumber === '') {
            throw new InvalidArgumentException('find-tx requires a transaction number argument.');
        }

        $paymentAccountId = $this->option('payment-account');

        if (! is_string($paymentAccountId) || $paymentAccountId === '') {
            throw new InvalidArgumentException(
                'find-tx requires --payment-account=<id> referencing a row in payment_accounts.',
            );
        }

        $account = PaymentAccount::query()->whereKey((int) $paymentAccountId)->first();

        if ($account === null) {
            throw new InvalidArgumentException("Payment account [{$paymentAccountId}] was not found.");
        }

        return $probe->findTransaction(
            $transactionNumber,
            $paymentAccountMapper->toGatewayAccount($account),
        );
    }

    private function assertConfigured(): void
    {
        $config = GatewayProviderConfig::forProvider('apisyria');

        if ($config->baseUrl === '' || $config->apiKey === '') {
            throw new InvalidArgumentException(
                'Set APISYRIA_BASE_URL and APISYRIA_API_KEY in .env before running apisyria:probe.',
            );
        }
    }
}
