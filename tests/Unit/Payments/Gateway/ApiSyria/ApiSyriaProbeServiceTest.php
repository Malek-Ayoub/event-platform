<?php

namespace Tests\Unit\Payments\Gateway\ApiSyria;

use App\Services\Payments\Gateway\ApiSyria\ApiSyriaProbeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiSyriaProbeServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function status_calls_resource_status_endpoint(): void
    {
        config([
            'payment_gateways.providers.apisyria.base_url' => 'https://api.syria.test',
            'payment_gateways.providers.apisyria.api_key' => 'secret-key',
        ]);

        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $this->assertSame('status', $query['resource'] ?? null);

            return Http::response([
                'success' => true,
                'message' => 'API is working.',
            ]);
        });

        $payload = app(ApiSyriaProbeService::class)->status();

        $this->assertTrue($payload['success']);
    }

    #[Test]
    public function list_accounts_calls_accounts_list_endpoint(): void
    {
        config([
            'payment_gateways.providers.apisyria.base_url' => 'https://api.syria.test',
            'payment_gateways.providers.apisyria.api_key' => 'secret-key',
        ]);

        Http::fake(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $this->assertSame('accounts', $query['resource'] ?? null);
            $this->assertSame('list', $query['action'] ?? null);

            return Http::response([
                'success' => true,
                'data' => [
                    'syriatel' => [['gsm' => '0933123456']],
                    'shamcash' => [['account_address' => '251awMERCHANT']],
                ],
            ]);
        });

        $payload = app(ApiSyriaProbeService::class)->listAccounts();

        $this->assertArrayHasKey('data', $payload);
    }
}
