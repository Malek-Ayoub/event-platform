<?php

namespace Tests\Unit\Services\Payments;

use App\Services\ActivityLogService;
use App\Services\OutboxService;
use App\Services\Payments\PaymentService;
use App\Services\TransactionRunner;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class PaymentServiceArchitectureTest extends TestCase
{
    /** @var list<class-string> */
    private array $paymentDomainServices = [
        PaymentService::class,
    ];

    #[Test]
    public function payment_service_uses_transaction_runner(): void
    {
        $parameters = $this->constructorParameterTypes(PaymentService::class);

        $this->assertContains(TransactionRunner::class, $parameters);
    }

    #[Test]
    public function payment_service_uses_activity_log_and_outbox_services(): void
    {
        $parameters = $this->constructorParameterTypes(PaymentService::class);

        $this->assertContains(ActivityLogService::class, $parameters);
        $this->assertContains(OutboxService::class, $parameters);
    }

    #[Test]
    public function payment_domain_services_do_not_call_db_transaction_directly(): void
    {
        foreach ($this->paymentDomainServices as $serviceClass) {
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
    public function payment_service_does_not_reference_refund_commission_or_notification(): void
    {
        $path = (new ReflectionClass(PaymentService::class))->getFileName();
        $this->assertIsString($path);
        $source = file_get_contents($path);
        $this->assertIsString($source);

        foreach ([
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
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source,
                "PaymentService must not reference {$forbidden}",
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
