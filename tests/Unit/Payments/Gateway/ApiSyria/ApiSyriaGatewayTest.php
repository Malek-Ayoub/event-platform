<?php

namespace Tests\Unit\Payments\Gateway\ApiSyria;

use App\DTOs\Payments\Gateway\GatewayPaymentAccount;
use App\DTOs\Payments\Gateway\VerifyTransactionRequest;
use App\Enums\Payments\GatewayOutcome;
use App\Enums\Payments\PaymentWalletProvider;
use App\Services\Payments\Gateway\ApiSyria\ApiSyriaGateway;
use App\Services\Payments\Gateway\ApiSyria\ApiSyriaHttpClient;
use App\Services\Payments\Gateway\ApiSyria\ApiSyriaResponseParser;
use App\Services\Payments\Gateway\Support\GatewayResponseMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiSyriaGatewayTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_calls_shamcash_find_tx_with_query_parameters_and_api_key_header(): void
    {
        config([
            'payment_gateways.providers.apisyria.base_url' => 'https://api.syria.test',
            'payment_gateways.providers.apisyria.api_key' => 'secret-key',
        ]);

        Http::fake(function ($request) {
            $this->assertSame('GET', $request->method());
            $this->assertSame('secret-key', $request->header('X-Api-Key')[0] ?? null);
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $this->assertSame('shamcash', $query['resource'] ?? null);
            $this->assertSame('find_tx', $query['action'] ?? null);
            $this->assertSame('TX-123', $query['tx'] ?? null);
            $this->assertSame('251awMERCHANT', $query['account_address'] ?? null);

            return Http::response([
                'success' => true,
                'data' => [
                    'found' => true,
                    'transaction' => [
                        'tran_id' => 'TX-123',
                        'currency' => 'USD',
                        'amount' => '120.00',
                        'account' => '251awMERCHANT',
                    ],
                ],
            ]);
        });

        $response = $this->gateway()->verifyTransaction(new VerifyTransactionRequest(
            transactionNumber: 'TX-123',
            expectedAmount: '120.00',
            expectedCurrency: 'USD',
            paymentAccount: new GatewayPaymentAccount(
                provider: PaymentWalletProvider::ShamCash,
                accountIdentifier: '251awMERCHANT',
                currency: 'USD',
            ),
        ));

        $this->assertSame(GatewayOutcome::Success, $response->outcome);
        $this->assertTrue($response->found);
        $this->assertSame('TX-123', $response->providerTransactionId);
        $this->assertSame('120.00', $response->amount);
        $this->assertSame('USD', $response->currency);
        $this->assertSame('251awMERCHANT', $response->receiverAccount);
    }

    #[Test]
    public function it_calls_syriatel_find_tx_with_gsm_parameter(): void
    {
        config([
            'payment_gateways.providers.apisyria.base_url' => 'https://api.syria.test',
            'payment_gateways.providers.apisyria.api_key' => 'secret-key',
        ]);

        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $this->assertSame('syriatel', $query['resource'] ?? null);
            $this->assertSame('0933123456', $query['gsm'] ?? null);

            return Http::response([
                'success' => true,
                'data' => [
                    'found' => true,
                    'transaction' => [
                        'transaction_no' => '456789',
                        'to' => '0933123456',
                        'amount' => '120.00',
                    ],
                ],
            ]);
        });

        $response = $this->gateway()->verifyTransaction(new VerifyTransactionRequest(
            transactionNumber: '456789',
            expectedAmount: '120.00',
            expectedCurrency: 'SYP',
            paymentAccount: new GatewayPaymentAccount(
                provider: PaymentWalletProvider::Syriatel,
                accountIdentifier: '0933123456',
                currency: 'SYP',
            ),
        ));

        $this->assertTrue($response->found);
        $this->assertSame('456789', $response->providerTransactionId);
        $this->assertSame('SYP', $response->currency);
    }

    #[Test]
    public function it_maps_not_found_as_successful_lookup_with_found_false(): void
    {
        config([
            'payment_gateways.providers.apisyria.base_url' => 'https://api.syria.test',
            'payment_gateways.providers.apisyria.api_key' => 'secret-key',
        ]);

        Http::fake(function ($request) {
            if (! str_starts_with($request->url(), 'https://api.syria.test')) {
                return null;
            }

            return Http::response([
                'success' => true,
                'data' => [
                    'found' => false,
                    'tran_id' => 'MISSING',
                ],
            ]);
        });

        $response = $this->gateway()->verifyTransaction(new VerifyTransactionRequest(
            transactionNumber: 'MISSING',
            expectedAmount: '120.00',
            expectedCurrency: 'USD',
            paymentAccount: new GatewayPaymentAccount(
                provider: PaymentWalletProvider::ShamCash,
                accountIdentifier: '251awMERCHANT',
                currency: 'USD',
            ),
        ));

        $this->assertSame(GatewayOutcome::Success, $response->outcome);
        $this->assertFalse($response->found);
    }

    private function gateway(): ApiSyriaGateway
    {
        return new ApiSyriaGateway(
            http: app(ApiSyriaHttpClient::class),
            parser: new ApiSyriaResponseParser,
            mapper: new GatewayResponseMapper,
        );
    }
}
