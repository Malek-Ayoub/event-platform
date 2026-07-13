<?php

namespace Tests\Unit\Services\Settlements;

use App\Services\Settlements\SettlementEntryService;
use App\Services\TransactionRunner;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class SettlementEntryServiceArchitectureTest extends TestCase
{
    #[Test]
    public function settlement_entry_service_uses_transaction_runner(): void
    {
        $parameters = $this->constructorParameterTypes(SettlementEntryService::class);

        $this->assertContains(TransactionRunner::class, $parameters);
    }

    #[Test]
    public function settlement_entry_service_does_not_call_db_transaction_directly(): void
    {
        $path = (new ReflectionClass(SettlementEntryService::class))->getFileName();
        $this->assertIsString($path);

        $source = file_get_contents($path);
        $this->assertIsString($source);
        $this->assertStringNotContainsString('DB::transaction', $source);
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
