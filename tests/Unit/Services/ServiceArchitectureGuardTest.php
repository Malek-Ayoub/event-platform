<?php

namespace Tests\Unit\Services;

use App\Services\ActivityLogService;
use App\Services\Commissions\CommissionService;
use App\Services\Orders\OrderService;
use App\Services\Orders\TicketSerialService;
use App\Services\Orders\TicketService;
use App\Services\OutboxService;
use App\Services\Payments\PaymentService;
use App\Services\PlatformSettings\PlatformSettingService;
use App\Services\Refunds\RefundService;
use App\Services\TransactionRunner;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class ServiceArchitectureGuardTest extends TestCase
{
    /** @var list<class-string> */
    private array $stateChangingDomainServices = [
        OrderService::class,
        PaymentService::class,
        RefundService::class,
        CommissionService::class,
        PlatformSettingService::class,
    ];

    /** @var list<class-string> */
    private array $orchestratedChildServices = [
        TicketService::class,
        TicketSerialService::class,
    ];

    /** @var list<string> */
    private array $domainServiceDirectories = [
        'Events',
        'Commerce',
        'TaxRates',
        'Orders',
        'Payments',
        'Refunds',
        'Commissions',
        'PlatformSettings',
    ];

    /** @var list<string> */
    private array $financialServiceSources = [
        'app/Services/Orders/OrderService.php',
        'app/Services/Payments/PaymentService.php',
        'app/Services/Refunds/RefundService.php',
        'app/Services/Commissions/CommissionService.php',
    ];

    /** @var array<string, list<string>> */
    private array $forbiddenServiceReferences = [
        'app/Services/Orders/OrderService.php' => [
            'PaymentTransaction::',
            'Refund::',
            'Commission::',
            'CommissionService',
            'PaymentService',
            'NotificationService',
            'Mail::',
        ],
        'app/Services/Payments/PaymentService.php' => [
            'RefundService',
            'Refund::',
            'CommissionService',
            'Commission::',
            'NotificationService',
            'Mail::',
            'TicketService',
            'Ticket::',
            'TicketType',
            'Reservation',
        ],
        'app/Services/Refunds/RefundService.php' => [
            'CommissionService',
            'CommissionAdjustment',
            'Commission::',
            'PaymentService',
            'NotificationService',
            'Mail::',
        ],
        'app/Services/Commissions/CommissionService.php' => [
            'PaymentService',
            'RefundService',
            'OrderService',
            'NotificationService',
            'Mail::',
            '$order->update',
            '$payment->update',
            '$refund->update',
        ],
        'app/Services/PlatformSettings/PlatformSettingService.php' => [
            'Order::',
            'PaymentTransaction::',
            'Commission::',
            'NotificationService',
            'Mail::',
        ],
    ];

    private function normalizedPath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    #[Test]
    public function domain_services_do_not_call_db_transaction_directly(): void
    {
        foreach ($this->domainServicePhpFiles() as $path) {
            $source = file_get_contents($path);
            $this->assertIsString($source);

            $this->assertStringNotContainsString(
                'DB::transaction',
                $source,
                basename($path).' must not call DB::transaction() directly',
            );
        }
    }

    #[Test]
    public function only_transaction_runner_uses_db_transaction_in_services(): void
    {
        $allowedPaths = array_filter(array_map(
            static fn (string $path): ?string => realpath($path) ?: null,
            [
                base_path('app/Services/TransactionRunner.php'),
                base_path('app/Services/Auth/AuthService.php'),
                base_path('app/Services/Auth/RegisterService.php'),
                base_path('app/Services/Auth/TokenService.php'),
                base_path('app/Services/Authorization/PermissionService.php'),
            ],
        ));

        $violations = [];

        foreach ($this->phpFilesIn(base_path('app/Services')) as $path) {
            $source = file_get_contents($path);
            $this->assertIsString($source);

            if (! str_contains($source, 'DB::transaction')) {
                continue;
            }

            $resolvedPath = realpath($path) ?: $path;

            if (in_array($resolvedPath, $allowedPaths, true)) {
                continue;
            }

            $violations[] = basename($path);
        }

        $this->assertSame([], $violations, 'Only approved infrastructure/auth services may call DB::transaction()');
    }

    #[Test]
    public function state_changing_domain_services_use_transaction_runner(): void
    {
        foreach ($this->stateChangingDomainServices as $serviceClass) {
            $parameters = $this->constructorParameterTypes($serviceClass);

            $this->assertContains(
                TransactionRunner::class,
                $parameters,
                "{$serviceClass} must depend on TransactionRunner",
            );
        }
    }

    #[Test]
    public function state_changing_domain_services_use_activity_log_and_outbox_services(): void
    {
        foreach ($this->stateChangingDomainServices as $serviceClass) {
            $parameters = $this->constructorParameterTypes($serviceClass);

            $this->assertContains(ActivityLogService::class, $parameters, "{$serviceClass} must use ActivityLogService");
            $this->assertContains(OutboxService::class, $parameters, "{$serviceClass} must use OutboxService");
        }
    }

    #[Test]
    public function orchestrated_child_services_do_not_write_audit_or_outbox_directly(): void
    {
        foreach ($this->orchestratedChildServices as $serviceClass) {
            $path = (new ReflectionClass($serviceClass))->getFileName();
            $this->assertIsString($path);
            $source = file_get_contents($path);
            $this->assertIsString($source);

            foreach (['ActivityLogService', 'OutboxService', 'ActivityLog::', 'OutboxEvent::'] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $source,
                    "{$serviceClass} must not write audit/outbox directly",
                );
            }
        }
    }

    #[Test]
    public function domain_services_do_not_write_activity_log_or_outbox_events_directly(): void
    {
        foreach ($this->domainServicePhpFiles() as $path) {
            $source = file_get_contents($path);
            $this->assertIsString($source);

            $this->assertStringNotContainsString(
                'ActivityLog::query()->create',
                $source,
                basename($path).' must use ActivityLogService',
            );
            $this->assertStringNotContainsString(
                'OutboxEvent::query()->create',
                $source,
                basename($path).' must use OutboxService',
            );
        }
    }

    #[Test]
    public function only_platform_setting_service_mutates_platform_setting_model(): void
    {
        foreach ($this->phpFilesIn(base_path('app/Services')) as $path) {
            if (basename($path) === 'PlatformSettingService.php') {
                continue;
            }

            if (str_contains($this->normalizedPath($path), 'PlatformSettings/Data/')) {
                continue;
            }

            $source = file_get_contents($path);
            $this->assertIsString($source);

            foreach (['PlatformSetting::query()->create', 'PlatformSetting::query()->update', '$platformSetting->update', '$setting->updateWithVersion'] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $source,
                    basename($path).' must not mutate PlatformSetting directly',
                );
            }
        }
    }

    #[Test]
    public function only_payment_service_marks_orders_as_paid(): void
    {
        foreach ($this->domainServicePhpFiles() as $path) {
            if (str_contains($this->normalizedPath($path), 'PaymentService.php')) {
                continue;
            }

            $source = file_get_contents($path);
            $this->assertIsString($source);

            $this->assertStringNotContainsString(
                "'status' => OrderStatus::Paid",
                $source,
                basename($path).' must not set Order to paid — PaymentService is the SSOT',
            );
        }
    }

    #[Test]
    public function financial_domain_services_do_not_reference_forbidden_dependencies(): void
    {
        foreach ($this->forbiddenServiceReferences as $relativePath => $forbiddenReferences) {
            $path = base_path($relativePath);
            $this->assertFileExists($path);

            $source = file_get_contents($path);
            $this->assertIsString($source);

            foreach ($forbiddenReferences as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $source,
                    "{$relativePath} must not reference {$forbidden}",
                );
            }
        }
    }

    #[Test]
    public function controllers_do_not_import_business_domain_models(): void
    {
        $forbiddenModels = [
            'Order',
            'Ticket',
            'PaymentTransaction',
            'Refund',
            'Commission',
            'CommissionAdjustment',
            'PlatformSetting',
            'Event',
            'Reservation',
        ];

        foreach ($this->phpFilesIn(base_path('app/Http/Controllers')) as $path) {
            $source = file_get_contents($path);
            $this->assertIsString($source);

            foreach ($forbiddenModels as $model) {
                $this->assertStringNotContainsString(
                    "App\\Models\\{$model}",
                    $source,
                    basename($path)." must not import {$model} directly — use services in Phase 6+",
                );
            }
        }
    }

    /**
     * @param  class-string  $class
     * @return list<string|null>
     */
    private function constructorParameterTypes(string $class): array
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);

        return array_map(
            fn ($parameter) => $parameter->getType() instanceof \ReflectionNamedType
                ? $parameter->getType()->getName()
                : null,
            $constructor->getParameters(),
        );
    }

    /**
     * @return list<string>
     */
    private function domainServicePhpFiles(): array
    {
        $files = [];

        foreach ($this->domainServiceDirectories as $directory) {
            $path = base_path("app/Services/{$directory}");
            if (is_dir($path)) {
                array_push($files, ...$this->phpFilesIn($path));
            }
        }

        return array_values(array_filter(
            $files,
            fn (string $path) => ! str_contains($path, '/Data/') && ! str_contains($path, 'StateMachine.php'),
        ));
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
