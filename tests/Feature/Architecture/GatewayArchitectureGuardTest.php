<?php

namespace Tests\Feature\Architecture;

use App\Contracts\Payments\GatewaySignatureVerifier;
use App\Contracts\Payments\PaymentGateway;
use App\Contracts\Payments\RefundGateway;
use App\Providers\PaymentGatewayServiceProvider;
use App\Services\Commissions\CommissionService;
use App\Services\Orders\OrderService;
use App\Services\Payments\Gateway\Http\PaymentGatewayHttpClient;
use App\Services\Payments\Gateway\PaymentGatewayRegistry;
use App\Services\Payments\Gateway\Support\GatewayResponseMapper;
use App\Services\Payments\PaymentService;
use App\Services\Refunds\RefundService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

/**
 * Phase 7.1 gateway layer architecture guard (extended in Phase 7.5).
 */
class GatewayArchitectureGuardTest extends TestCase
{
    /** @var list<class-string> */
    private array $gatewayImplementationClasses = [];

    /** @var list<class-string> */
    private array $gatewayContractClasses = [
        PaymentGateway::class,
        RefundGateway::class,
        GatewaySignatureVerifier::class,
    ];

    /** @var list<class-string> */
    private array $domainServicesForbiddenFromGatewayDtos = [
        PaymentService::class,
        RefundService::class,
        CommissionService::class,
        OrderService::class,
    ];

    /** @var list<string> */
    private array $forbiddenGatewayPatterns = [
        'App\\Models\\',
        'DB::',
        'Illuminate\\Support\\Facades\\',
        'Illuminate\\Http\\Client\\',
        'GuzzleHttp\\',
        'curl_',
        'Http::',
        'PaymentService',
        'RefundService',
        'ActivityLogService',
        'OutboxService',
        'ActivityLog::',
        'OutboxEvent::',
        'TransactionRunner',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->gatewayImplementationClasses = $this->discoverGatewayImplementationClasses();
    }

    #[Test]
    public function gateway_implementations_do_not_import_eloquent_models(): void
    {
        foreach ($this->gatewayImplementationClasses as $class) {
            $source = $this->sourceOf($class);

            $this->assertDoesNotMatchRegularExpression(
                '/use App\\\\Models\\\\/',
                $source,
                "{$class} must not import App\\Models\\*",
            );
        }
    }

    #[Test]
    public function gateway_implementations_do_not_use_forbidden_integrations(): void
    {
        foreach ($this->gatewayImplementationClasses as $class) {
            $source = $this->sourceOf($class);

            foreach ($this->forbiddenGatewayPatterns as $pattern) {
                $this->assertStringNotContainsString(
                    $pattern,
                    $source,
                    "{$class} must not reference {$pattern} in the gateway layer",
                );
            }
        }
    }

    #[Test]
    public function gateway_contracts_do_not_depend_on_laravel_facades(): void
    {
        foreach ($this->gatewayContractClasses as $class) {
            $source = $this->sourceOf($class);

            $this->assertStringNotContainsString(
                'Illuminate\\Support\\Facades\\',
                $source,
                "{$class} must not depend on Laravel facades",
            );
        }
    }

    #[Test]
    public function payment_gateway_http_client_depends_on_http_client_interface_only(): void
    {
        $source = $this->sourceOf(PaymentGatewayHttpClient::class);

        $this->assertStringContainsString('HttpClientInterface', $source);
        $this->assertStringNotContainsString('Illuminate\\Http\\Client\\Factory', $source);
        $this->assertStringNotContainsString('Illuminate\\Support\\Facades\\', $source);
    }

    #[Test]
    public function gateway_response_mapper_does_not_depend_on_http_client_libraries(): void
    {
        $source = $this->sourceOf(GatewayResponseMapper::class);

        foreach (['Illuminate\\Http\\Client\\', 'GuzzleHttp\\', 'Http::'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
    }

    #[Test]
    public function domain_services_do_not_import_gateway_dtos(): void
    {
        foreach ($this->domainServicesForbiddenFromGatewayDtos as $class) {
            $source = $this->sourceOf($class);

            $this->assertDoesNotMatchRegularExpression(
                '/use App\\\\DTOs\\\\Payments\\\\Gateway\\\\/',
                $source,
                "{$class} must not import gateway DTOs — map via PaymentGatewayService in Phase 7.4",
            );
        }
    }

    #[Test]
    public function only_payment_gateway_service_may_use_gateway_registry_and_contracts(): void
    {
        $forbiddenPatterns = [
            'PaymentGatewayRegistry',
            'App\\Contracts\\Payments\\PaymentGateway',
            'App\\Contracts\\Payments\\RefundGateway',
            'App\\Contracts\\Payments\\GatewaySignatureVerifier',
        ];

        foreach ($this->discoverClassesForbiddenFromGatewayRegistryAccess() as $class) {
            $source = $this->sourceOf($class);

            foreach ($forbiddenPatterns as $pattern) {
                $this->assertStringNotContainsString(
                    $pattern,
                    $source,
                    "{$class} must not access gateway registry/contracts — use PaymentGatewayService (Anti-Corruption Layer)",
                );
            }
        }
    }

    #[Test]
    public function payment_gateway_registry_has_no_business_logic_dependencies(): void
    {
        $source = $this->sourceOf(PaymentGatewayRegistry::class);

        foreach (['PaymentService', 'RefundService', 'DB::', 'App\\Models\\'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
    }

    /**
     * @return list<class-string>
     */
    private function discoverClassesForbiddenFromGatewayRegistryAccess(): array
    {
        $classes = [];
        $allowed = array_filter([
            PaymentGatewayServiceProvider::class,
            class_exists('App\\Services\\Payments\\PaymentGatewayService')
                ? 'App\\Services\\Payments\\PaymentGatewayService'
                : null,
        ]);

        foreach ([
            base_path('app/Http/Controllers'),
            base_path('app/Services'),
        ] as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relative = str_replace(
                    [base_path('app').DIRECTORY_SEPARATOR, '.php', DIRECTORY_SEPARATOR],
                    ['', '', '\\'],
                    $file->getPathname(),
                );

                if (str_starts_with($relative, 'Services\\Payments\\Gateway\\')) {
                    continue;
                }

                $class = 'App\\'.$relative;

                if (! class_exists($class) || in_array($class, $allowed, true)) {
                    continue;
                }

                $reflection = new ReflectionClass($class);

                if ($reflection->isInterface() || $reflection->isAbstract()) {
                    continue;
                }

                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return list<class-string>
     */
    private function discoverGatewayImplementationClasses(): array
    {
        $classes = [];
        $directory = base_path('app/Services/Payments/Gateway');

        if (! is_dir($directory)) {
            return $classes;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(
                [base_path('app').DIRECTORY_SEPARATOR, '.php', DIRECTORY_SEPARATOR],
                ['', '', '\\'],
                $file->getPathname(),
            );

            $class = 'App\\'.$relative;

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isInterface() || $reflection->isAbstract()) {
                continue;
            }

            if ($reflection->implementsInterface(PaymentGateway::class)
                || $reflection->implementsInterface(RefundGateway::class)
                || $reflection->implementsInterface(GatewaySignatureVerifier::class)) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @param  class-string  $class
     */
    private function sourceOf(string $class): string
    {
        $source = file_get_contents((new ReflectionClass($class))->getFileName());
        $this->assertIsString($source);

        return $source;
    }
}
