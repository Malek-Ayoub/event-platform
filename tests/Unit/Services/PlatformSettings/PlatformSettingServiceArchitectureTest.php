<?php

namespace Tests\Unit\Services\PlatformSettings;

use App\Services\ActivityLogService;
use App\Services\OutboxService;
use App\Services\PlatformSettings\PlatformSettingService;
use App\Services\TransactionRunner;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class PlatformSettingServiceArchitectureTest extends TestCase
{
    #[Test]
    public function platform_setting_service_uses_transaction_runner_activity_log_and_outbox(): void
    {
        $reflection = new ReflectionClass(PlatformSettingService::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);

        $parameters = array_map(
            fn ($parameter) => $parameter->getType() instanceof \ReflectionNamedType
                ? $parameter->getType()->getName()
                : null,
            $constructor->getParameters(),
        );

        $this->assertContains(TransactionRunner::class, $parameters);
        $this->assertContains(ActivityLogService::class, $parameters);
        $this->assertContains(OutboxService::class, $parameters);
    }

    #[Test]
    public function platform_setting_service_does_not_call_db_transaction_directly(): void
    {
        $path = (new ReflectionClass(PlatformSettingService::class))->getFileName();
        $this->assertIsString($path);
        $source = file_get_contents($path);
        $this->assertIsString($source);
        $this->assertStringNotContainsString('DB::transaction', $source);
    }
}
