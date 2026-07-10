<?php

namespace Tests\Feature\Architecture;

use App\Console\Commands\OutboxProcessCommand;
use App\Jobs\ProcessOutboxEvents;
use App\Repositories\OutboxRepository;
use App\Services\Commissions\CommissionService;
use App\Services\Orders\OrderService;
use App\Services\Outbox\OutboxDispatcher;
use App\Services\Outbox\OutboxConsumerRegistry;
use App\Services\Payments\PaymentService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class OutboxArchitectureGuardTest extends TestCase
{
    /** @var list<class-string> */
    private array $allowedOutboxConsumerReferences = [
        OutboxDispatcher::class,
        OutboxConsumerRegistry::class,
        OutboxProcessCommand::class,
        ProcessOutboxEvents::class,
    ];

    /** @var list<string> */
    private array $forbiddenOutboxWorkerSymbols = [
        'OutboxDispatcher',
        'OutboxRepository',
        'OutboxConsumerRegistry',
        'App\\Contracts\\Outbox\\OutboxConsumer',
        'OutboxTenantScope',
        'ProcessOutboxEvents',
    ];

    #[Test]
    public function domain_services_do_not_consume_outbox_directly(): void
    {
        foreach ([
            OrderService::class,
            PaymentService::class,
            CommissionService::class,
        ] as $serviceClass) {
            $source = $this->sourceOf($serviceClass);

            foreach ($this->forbiddenOutboxWorkerSymbols as $symbol) {
                $this->assertStringNotContainsString(
                    $symbol,
                    $source,
                    "{$serviceClass} must not consume outbox directly — only OutboxService::record() inside transactions",
                );
            }
        }
    }

    #[Test]
    public function only_worker_layer_may_reference_outbox_dispatcher_or_repository(): void
    {
        foreach ($this->discoverPhpClassesUnder(base_path('app')) as $class) {
            if (in_array($class, $this->allowedOutboxConsumerReferences, true)) {
                continue;
            }

            if (str_starts_with($class, 'App\\Services\\Outbox\\')) {
                continue;
            }

            if ($class === \App\Providers\OutboxServiceProvider::class) {
                continue;
            }

            if ($class === OutboxRepository::class) {
                continue;
            }

            if (str_starts_with($class, 'App\\Contracts\\Outbox\\')) {
                continue;
            }

            $source = $this->sourceOf($class);

            $this->assertStringNotContainsString(
                'OutboxDispatcher',
                $source,
                "{$class} must not reference OutboxDispatcher — enqueue ProcessOutboxEvents or run outbox:process",
            );

            $this->assertStringNotContainsString(
                'OutboxRepository',
                $source,
                "{$class} must not reference OutboxRepository",
            );
        }
    }

    #[Test]
    public function outbox_repository_is_only_referenced_from_allowed_layers(): void
    {
        $allowed = [
            OutboxRepository::class,
            OutboxDispatcher::class,
            OutboxProcessCommand::class,
            ProcessOutboxEvents::class,
            \App\Providers\OutboxServiceProvider::class,
        ];

        foreach ($this->discoverPhpClassesUnder(base_path('app')) as $class) {
            if (in_array($class, $allowed, true)) {
                continue;
            }

            $this->assertStringNotContainsString(
                'OutboxRepository',
                $this->sourceOf($class),
                "{$class} must not reference OutboxRepository",
            );
        }
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
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
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
}
