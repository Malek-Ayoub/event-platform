<?php

namespace Tests\Unit\Models\OrdersDomain;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketSerialCounter;
use App\Support\Concerns\BelongsToVenue;
use App\Support\Concerns\HasOptimisticLock;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderDomainConstraintsTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<class-string> */
    private array $models = [
        Order::class,
        Ticket::class,
        TicketSerialCounter::class,
    ];

    #[Test]
    public function order_domain_models_use_belongs_to_venue(): void
    {
        foreach ($this->models as $modelClass) {
            $traits = class_uses_recursive($modelClass);
            $this->assertContains(
                BelongsToVenue::class,
                $traits,
                "{$modelClass} should use BelongsToVenue",
            );
        }
    }

    #[Test]
    public function order_domain_models_do_not_use_optimistic_lock(): void
    {
        foreach ($this->models as $modelClass) {
            $traits = class_uses_recursive($modelClass);
            $this->assertNotContains(
                HasOptimisticLock::class,
                $traits,
                "{$modelClass} must not use HasOptimisticLock",
            );
        }
    }

    #[Test]
    public function order_domain_models_do_not_use_soft_deletes_trait(): void
    {
        foreach ($this->models as $modelClass) {
            $this->assertNotContains(
                SoftDeletes::class,
                class_uses_recursive($modelClass),
                "{$modelClass} must not use SoftDeletes",
            );
        }
    }
}
