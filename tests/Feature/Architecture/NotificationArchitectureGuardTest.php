<?php

namespace Tests\Feature\Architecture;

use App\Providers\NotificationServiceProvider;
use App\Services\Commissions\CommissionService;
use App\Services\Notifications\Channels\EmailChannel;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\NotificationRegistry;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentService;
use App\Services\Refunds\RefundService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class NotificationArchitectureGuardTest extends TestCase
{
    /** @var list<string> */
    private array $forbiddenNotificationSymbols = [
        'NotificationDispatcher',
        'NotificationRegistry',
        'EmailChannel',
        'EmailSenderInterface',
        'Mail::',
        'Illuminate\\Mail\\',
        'Illuminate\\Notifications\\',
    ];

    #[Test]
    public function financial_domain_services_do_not_dispatch_notifications_directly(): void
    {
        foreach ([
            OrderService::class,
            PaymentService::class,
            RefundService::class,
            CommissionService::class,
        ] as $serviceClass) {
            $source = $this->sourceOf($serviceClass);

            foreach ($this->forbiddenNotificationSymbols as $symbol) {
                $this->assertStringNotContainsString(
                    $symbol,
                    $source,
                    "{$serviceClass} must not dispatch notifications directly — record OutboxEvent instead",
                );
            }
        }
    }

    #[Test]
    public function only_notification_and_outbox_layers_may_reference_the_dispatcher(): void
    {
        $allowed = [
            NotificationDispatcher::class,
            NotificationRegistry::class,
            NotificationServiceProvider::class,
            EmailChannel::class,
        ];

        foreach ($this->discoverPhpClassesUnder(base_path('app')) as $class) {
            if (in_array($class, $allowed, true)) {
                continue;
            }

            if (str_starts_with($class, 'App\\Services\\Notifications\\')) {
                continue;
            }

            if (str_starts_with($class, 'App\\Services\\Outbox\\')) {
                continue;
            }

            if (str_starts_with($class, 'App\\Contracts\\Notifications\\')) {
                continue;
            }

            $this->assertStringNotContainsString(
                'NotificationDispatcher',
                $this->sourceOf($class),
                "{$class} must not reference NotificationDispatcher — use an Outbox consumer",
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
