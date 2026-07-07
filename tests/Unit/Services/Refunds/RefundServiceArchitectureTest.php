<?php

namespace Tests\Unit\Services\Refunds;

use App\Services\ActivityLogService;
use App\Services\OutboxService;
use App\Services\Refunds\RefundService;
use App\Services\TransactionRunner;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class RefundServiceArchitectureTest extends TestCase
{
    /** @var list<class-string> */
    private array $refundDomainServices = [
        RefundService::class,
    ];

    #[Test]
    public function refund_service_uses_transaction_runner(): void
    {
        $parameters = $this->constructorParameterTypes(RefundService::class);

        $this->assertContains(TransactionRunner::class, $parameters);
    }

    #[Test]
    public function refund_service_uses_activity_log_and_outbox_services(): void
    {
        $parameters = $this->constructorParameterTypes(RefundService::class);

        $this->assertContains(ActivityLogService::class, $parameters);
        $this->assertContains(OutboxService::class, $parameters);
    }

    #[Test]
    public function refund_domain_services_do_not_call_db_transaction_directly(): void
    {
        foreach ($this->refundDomainServices as $serviceClass) {
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
    public function refund_service_does_not_reference_commission_or_notification(): void
    {
        $path = (new ReflectionClass(RefundService::class))->getFileName();
        $this->assertIsString($path);
        $source = file_get_contents($path);
        $this->assertIsString($source);

        foreach ([
            'CommissionService',
            'CommissionAdjustment',
            'Commission::',
            'PaymentService',
            'NotificationService',
            'Mail::',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source,
                "RefundService must not reference {$forbidden}",
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
