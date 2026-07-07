<?php

namespace Tests\Unit\Models\OrdersDomain;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketSerialCounter;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class OrderDomainModelQualityTest extends TestCase
{
    /** @var list<class-string> */
    private array $models = [
        Order::class,
        Ticket::class,
        TicketSerialCounter::class,
    ];

    #[Test]
    public function order_domain_models_do_not_define_appends(): void
    {
        foreach ($this->models as $modelClass) {
            $model = new $modelClass;
            $this->assertSame([], $model->getAppends(), "{$modelClass} must not define \$appends");
        }
    }

    #[Test]
    public function order_domain_models_do_not_define_accessors(): void
    {
        foreach ($this->models as $modelClass) {
            $reflection = new ReflectionClass($modelClass);
            foreach ($reflection->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $modelClass) {
                    continue;
                }

                $name = $method->getName();
                if (str_starts_with($name, 'get') && str_ends_with($name, 'Attribute')) {
                    if (in_array($name, ['getUseFactoryAttribute'], true)) {
                        continue;
                    }

                    $this->fail("{$modelClass} defines accessor {$name} — models must stay anemic in Phase 4");
                }
            }

            $this->assertTrue(true);
        }
    }

    #[Test]
    public function order_domain_models_do_not_use_attribute_mutators(): void
    {
        foreach ($this->models as $modelClass) {
            $reflection = new ReflectionClass($modelClass);
            foreach ($reflection->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $modelClass) {
                    continue;
                }

                $returnType = $method->getReturnType();
                if ($returnType === null) {
                    continue;
                }

                $returnTypeName = $returnType instanceof \ReflectionNamedType ? $returnType->getName() : '';
                if ($returnTypeName === 'Illuminate\Database\Eloquent\Casts\Attribute') {
                    $this->fail("{$modelClass} defines Attribute mutator {$method->getName()} — not allowed in Phase 4");
                }
            }

            $this->assertTrue(true);
        }
    }
}
