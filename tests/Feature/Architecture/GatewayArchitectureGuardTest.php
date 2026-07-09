<?php

namespace Tests\Feature\Architecture;

use App\Contracts\Payments\GatewaySignatureVerifier;
use App\Contracts\Payments\PaymentGateway;
use App\Contracts\Payments\PaymentVerificationGateway;
use App\Contracts\Payments\RefundGateway;
use App\Providers\PaymentGatewayServiceProvider;
use App\Services\Commissions\CommissionService;
use App\Services\Orders\OrderService;
use App\Services\Payments\Gateway\Http\PaymentGatewayHttpClient;
use App\Services\Payments\Gateway\PaymentGatewayRegistry;
use App\Services\Payments\Gateway\Support\GatewayResponseMapper;
use App\Services\Payments\Mapping\InitiatePaymentRequestMapper;
use App\Services\Payments\Mapping\InitiatePaymentResponseMapper;
use App\Services\Payments\Mapping\RefundRequestMapper;
use App\Services\Payments\Mapping\VerifyTransactionRequestMapper;
use App\Services\Payments\Mapping\VerifyTransactionResponseMapper;
use App\Services\Payments\PaymentGatewayService;
use App\Services\Payments\PaymentInstructionService;
use App\Services\Payments\PaymentService;
use App\Services\Payments\PaymentVerificationService;
use App\Services\Refunds\RefundService;
use App\Services\Webhooks\WebhookService;
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
        PaymentVerificationGateway::class,
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
            'App\\Contracts\\Payments\\PaymentVerificationGateway',
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
    public function webhook_service_is_orchestrator_only(): void
    {
        $source = $this->sourceOf(WebhookService::class);

        foreach ([
            'PaymentGatewayRegistry',
            'GatewaySignatureVerifier',
            'PaymentService',
            'RefundService',
            'WebhookLogService',
            'ReplayProtectionService',
            'App\\Contracts\\Payments\\PaymentGateway',
            'App\\Contracts\\Payments\\RefundGateway',
            'App\\DTOs\\Payments\\Gateway\\',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source,
                "WebhookService must remain a thin orchestrator and must not reference {$forbidden}",
            );
        }

        $this->assertStringContainsString('PaymentGatewayService', $source);
    }

    #[Test]
    public function payment_gateway_service_has_no_provider_specific_branching(): void
    {
        $source = $this->sourceOf(PaymentGatewayService::class);

        $this->assertDoesNotMatchRegularExpression(
            '/\$provider\s*===/',
            $source,
            'PaymentGatewayService must not branch on provider slug equality',
        );

        $this->assertDoesNotMatchRegularExpression(
            '/match\s*\(\s*\$.*provider/',
            $source,
            'PaymentGatewayService must not match on provider slug',
        );

        foreach (['shamcash', 'syriatel_cash', 'SyriatelCash', 'ShamCash'] as $providerLiteral) {
            $this->assertStringNotContainsString(
                "'{$providerLiteral}'",
                $source,
                'PaymentGatewayService must not hard-code provider names',
            );
        }
    }

    #[Test]
    public function payment_gateway_registry_is_only_referenced_from_allowed_layers(): void
    {
        foreach ($this->discoverClassesForbiddenFromSymbol('PaymentGatewayRegistry') as $class) {
            $this->assertStringNotContainsString(
                'PaymentGatewayRegistry',
                $this->sourceOf($class),
                "{$class} must not reference PaymentGatewayRegistry — use PaymentGatewayService",
            );
        }
    }

    #[Test]
    public function gateway_contracts_are_only_referenced_from_allowed_layers(): void
    {
        $forbiddenPatterns = [
            'App\\Contracts\\Payments\\PaymentGateway',
            'App\\Contracts\\Payments\\RefundGateway',
            'App\\Contracts\\Payments\\GatewaySignatureVerifier',
            'App\\Contracts\\Payments\\PaymentVerificationGateway',
        ];

        foreach ($this->discoverClassesForbiddenFromGatewayContracts() as $class) {
            $source = $this->sourceOf($class);

            foreach ($forbiddenPatterns as $pattern) {
                $this->assertStringNotContainsString(
                    $pattern,
                    $source,
                    "{$class} must not reference gateway contracts — use PaymentGatewayService",
                );
            }
        }
    }

    #[Test]
    public function gateway_mappers_are_pure_mapping_only(): void
    {
        $forbiddenPatterns = [
            'App\\Models\\',
            'config(',
            'Config::',
            'DB::',
            'PaymentService',
            'RefundService',
            'OrderService',
            'TransactionRunner',
        ];

        foreach ([
            InitiatePaymentRequestMapper::class,
            InitiatePaymentResponseMapper::class,
            RefundRequestMapper::class,
        ] as $class) {
            $source = $this->sourceOf($class);

            foreach ($forbiddenPatterns as $pattern) {
                $this->assertStringNotContainsString(
                    $pattern,
                    $source,
                    "{$class} must remain pure mapping without {$pattern}",
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

    #[Test]
    public function payment_instruction_service_does_not_access_gateway_registry_or_contracts(): void
    {
        $source = $this->sourceOf(PaymentInstructionService::class);

        foreach ([
            'PaymentGatewayRegistry',
            'PaymentGatewayService',
            'App\\Contracts\\Payments\\PaymentGateway',
            'App\\Contracts\\Payments\\PaymentVerificationGateway',
            'App\\Contracts\\Payments\\RefundGateway',
            'App\\Contracts\\Payments\\GatewaySignatureVerifier',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
    }

    #[Test]
    public function payment_verification_service_accesses_gateway_only_via_payment_gateway_service(): void
    {
        $source = $this->sourceOf(PaymentVerificationService::class);

        $this->assertStringContainsString('PaymentGatewayService', $source);
        $this->assertStringNotContainsString('PaymentGatewayRegistry', $source);
        $this->assertStringNotContainsString('App\\Contracts\\Payments\\PaymentVerificationGateway', $source);
    }

    #[Test]
    public function manual_transfer_services_do_not_reference_dormant_webhook_infrastructure(): void
    {
        $forbidden = [
            'WebhookLog',
            'WebhookLogService',
            'WebhookService',
            'ReplayProtectionService',
            'GatewaySignatureVerifier',
            'WebhookDomainCommandMapperRegistry',
        ];

        foreach ([PaymentInstructionService::class, PaymentVerificationService::class] as $class) {
            $source = $this->sourceOf($class);

            foreach ($forbidden as $pattern) {
                $this->assertStringNotContainsString(
                    $pattern,
                    $source,
                    "{$class} must not reference dormant webhook infrastructure ({$pattern})",
                );
            }
        }
    }

    #[Test]
    public function verify_transaction_mappers_are_pure_mapping_only(): void
    {
        $forbiddenPatterns = [
            'App\\Models\\',
            'config(',
            'Config::',
            'DB::',
            'PaymentService',
            'RefundService',
            'OrderService',
            'TransactionRunner',
        ];

        foreach ([
            VerifyTransactionRequestMapper::class,
            VerifyTransactionResponseMapper::class,
        ] as $class) {
            $source = $this->sourceOf($class);

            foreach ($forbiddenPatterns as $pattern) {
                $this->assertStringNotContainsString(
                    $pattern,
                    $source,
                    "{$class} must remain pure mapping without {$pattern}",
                );
            }
        }
    }

    /**
     * @return list<class-string>
     */
    private function discoverClassesForbiddenFromGatewayRegistryAccess(): array
    {
        $classes = [];
        $allowed = [
            PaymentGatewayServiceProvider::class,
            PaymentGatewayService::class,
        ];

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
                || $reflection->implementsInterface(GatewaySignatureVerifier::class)
                || $reflection->implementsInterface(PaymentVerificationGateway::class)) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return list<class-string>
     */
    private function discoverClassesForbiddenFromSymbol(string $symbol): array
    {
        $classes = [];
        $allowed = [
            PaymentGatewayServiceProvider::class,
            PaymentGatewayService::class,
            PaymentGatewayRegistry::class,
        ];

        foreach ($this->discoverPhpClassesUnder(base_path('app')) as $class) {
            if (in_array($class, $allowed, true)) {
                continue;
            }

            if (str_starts_with($class, 'App\\Services\\Payments\\Gateway\\')) {
                continue;
            }

            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return list<class-string>
     */
    private function discoverClassesForbiddenFromGatewayContracts(): array
    {
        $classes = [];
        $allowed = [
            PaymentGatewayService::class,
            PaymentGatewayRegistry::class,
        ];

        foreach ($this->discoverPhpClassesUnder(base_path('app')) as $class) {
            if (in_array($class, $allowed, true)) {
                continue;
            }

            if (str_starts_with($class, 'App\\Services\\Payments\\Gateway\\')) {
                continue;
            }

            if (in_array($class, $this->gatewayContractClasses, true)) {
                continue;
            }

            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return list<class-string>
     */
    private function discoverPhpClassesUnder(string $directory): array
    {
        $classes = [];

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

            if (! class_exists($class) && ! interface_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isTrait()) {
                continue;
            }

            $classes[] = $class;
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
