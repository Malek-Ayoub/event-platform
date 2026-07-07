<?php

namespace Tests\Unit\Services\Orders;

use App\Services\ActivityLogService;
use App\Services\Orders\OrderService;
use App\Services\Orders\TicketSerialService;
use App\Services\Orders\TicketService;
use App\Services\OutboxService;
use App\Services\TransactionRunner;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class OrderServiceArchitectureTest extends TestCase
{
    /** @var list<class-string> */
    private array $orderDomainServices = [
        OrderService::class,
        TicketService::class,
        TicketSerialService::class,
    ];

    #[Test]
    public function order_service_uses_transaction_runner(): void
    {
        $reflection = new ReflectionClass(OrderService::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);

        $parameters = array_map(
            fn ($parameter) => $parameter->getType() instanceof \ReflectionNamedType
                ? $parameter->getType()->getName()
                : null,
            $constructor->getParameters(),
        );

        $this->assertContains(TransactionRunner::class, $parameters);
    }

    #[Test]
    public function order_service_uses_activity_log_and_outbox_services(): void
    {
        $reflection = new ReflectionClass(OrderService::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);

        $parameters = array_map(
            fn ($parameter) => $parameter->getType() instanceof \ReflectionNamedType
                ? $parameter->getType()->getName()
                : null,
            $constructor->getParameters(),
        );

        $this->assertContains(ActivityLogService::class, $parameters);
        $this->assertContains(OutboxService::class, $parameters);
        $this->assertContains(TicketService::class, $parameters);
    }

    #[Test]
    public function order_domain_services_do_not_call_db_transaction_directly(): void
    {
        foreach ($this->orderDomainServices as $serviceClass) {
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
    public function order_service_does_not_reference_payment_refund_or_commission_models(): void
    {
        $path = (new ReflectionClass(OrderService::class))->getFileName();
        $this->assertIsString($path);
        $source = file_get_contents($path);
        $this->assertIsString($source);

        foreach (['PaymentTransaction', 'Refund', 'Commission', 'NotificationService', 'Mail::'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source,
                "OrderService must not reference {$forbidden}",
            );
        }
    }

    #[Test]
    public function ticket_serial_service_does_not_write_audit_or_outbox_records(): void
    {
        foreach ([TicketSerialService::class, TicketService::class] as $serviceClass) {
            $path = (new ReflectionClass($serviceClass))->getFileName();
            $this->assertIsString($path);
            $source = file_get_contents($path);
            $this->assertIsString($source);

            $this->assertStringNotContainsString('ActivityLogService', $source, "{$serviceClass} must not use ActivityLogService");
            $this->assertStringNotContainsString('OutboxService', $source, "{$serviceClass} must not use OutboxService");
            $this->assertStringNotContainsString('ActivityLog::', $source, "{$serviceClass} must not write ActivityLog directly");
            $this->assertStringNotContainsString('OutboxEvent::', $source, "{$serviceClass} must not write OutboxEvent directly");
        }
    }
}
