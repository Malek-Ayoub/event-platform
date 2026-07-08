<?php

namespace Tests\Unit\Payments\Gateway;

use App\Enums\Payments\GatewayOutcome;
use App\Services\Payments\Gateway\Support\GatewayProviderConfig;
use App\Services\Payments\Gateway\Support\GatewayResponseMapper;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GatewayResponseMapperTest extends TestCase
{
    #[Test]
    public function it_classifies_http_responses_consistently(): void
    {
        $mapper = new GatewayResponseMapper;

        $this->assertSame(
            GatewayOutcome::Success,
            $mapper->classifyHttpResponse(new Response(new \GuzzleHttp\Psr7\Response(200)), []),
        );
        $this->assertSame(
            GatewayOutcome::Declined,
            $mapper->classifyHttpResponse(new Response(new \GuzzleHttp\Psr7\Response(422)), ['error' => 'declined']),
        );
        $this->assertSame(
            GatewayOutcome::ProviderError,
            $mapper->classifyHttpResponse(new Response(new \GuzzleHttp\Psr7\Response(503)), ['error' => 'down']),
        );
        $this->assertSame(
            GatewayOutcome::Timeout,
            $mapper->classifyHttpResponse(new Response(new \GuzzleHttp\Psr7\Response(504)), ['error' => 'timeout']),
        );
        $this->assertSame(
            GatewayOutcome::Unknown,
            $mapper->classifyHttpResponse(new Response(new \GuzzleHttp\Psr7\Response(200)), null),
        );
    }

    #[Test]
    public function gateway_provider_config_exposes_shared_http_policy(): void
    {
        config([
            'payment_gateways.http.connect_timeout' => 7,
            'payment_gateways.http.request_timeout' => 21,
            'payment_gateways.http.retry_attempts' => 3,
            'payment_gateways.http.retry_delay_ms' => 500,
        ]);

        $shamcash = GatewayProviderConfig::forProvider('shamcash');
        $syriatel = GatewayProviderConfig::forProvider('syriatel_cash');

        foreach ([$shamcash, $syriatel] as $config) {
            $this->assertSame(7, $config->connectTimeout);
            $this->assertSame(21, $config->requestTimeout);
            $this->assertSame(3, $config->retryAttempts);
            $this->assertSame(500, $config->retryDelayMs);
        }
    }
}
