<?php

namespace Tests\Unit\Services\Commissions;

use App\Services\ActivityLogService;
use App\Services\Commissions\CommissionService;
use App\Services\OutboxService;
use App\Services\TransactionRunner;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class CommissionServiceArchitectureTest extends TestCase
{
    /** @var list<class-string> */
    private array $commissionDomainServices = [
        CommissionService::class,
    ];

    #[Test]
    public function commission_service_uses_transaction_runner(): void
    {
        $parameters = $this->constructorParameterTypes(CommissionService::class);

        $this->assertContains(TransactionRunner::class, $parameters);
    }

    #[Test]
    public function commission_service_uses_activity_log_and_outbox_services(): void
    {
        $parameters = $this->constructorParameterTypes(CommissionService::class);

        $this->assertContains(ActivityLogService::class, $parameters);
        $this->assertContains(OutboxService::class, $parameters);
    }

    #[Test]
    public function commission_domain_services_do_not_call_db_transaction_directly(): void
    {
        foreach ($this->commissionDomainServices as $serviceClass) {
            $path = (new ReflectionClass($serviceClass))->getFileName();
            $this->assertIsString($path);

            $source = file_get_contents($path);
            $this->assertIsString($source);
            $this->assertStringNotContainsString(
                'DB::transaction',
                $source,
                "{$serviceClass} must not call DB::transaction() directly",
            );
        }
    }

    #[Test]
    public function commission_service_does_not_reference_payment_refund_or_notification(): void
    {
        $path = (new ReflectionClass(CommissionService::class))->getFileName();
        $this->assertIsString($path);
        $source = file_get_contents($path);
        $this->assertIsString($source);

        foreach ([
            'PaymentService',
            'RefundService',
            'OrderService',
            'NotificationService',
            'Mail::',
            '$order->update',
            '$payment->update',
            '$refund->update',
            'Refund::query()->create',
            'PaymentTransaction::query()->create',
            'Order::query()->create',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source,
                "CommissionService must not reference {$forbidden}",
            );
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
}
