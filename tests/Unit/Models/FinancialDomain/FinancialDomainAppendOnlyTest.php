<?php

namespace Tests\Unit\Models\FinancialDomain;

use App\Models\Commission;
use App\Models\CommissionAdjustment;
use App\Models\Event;
use App\Models\Order;
use App\Models\SettlementEntry;
use App\Support\Concerns\HasOptimisticLock;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class FinancialDomainAppendOnlyTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<class-string> */
    private array $appendOnlyModels = [
        Commission::class,
        CommissionAdjustment::class,
        SettlementEntry::class,
    ];

    #[Test]
    public function commission_and_commission_adjustment_disable_updated_at_constant(): void
    {
        $this->assertNull(Commission::UPDATED_AT);
        $this->assertNull(CommissionAdjustment::UPDATED_AT);
        $this->assertNull(SettlementEntry::UPDATED_AT);
    }

    #[Test]
    public function append_only_tables_have_no_updated_at_column(): void
    {
        $this->assertFalse(Schema::hasColumn('commissions', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('commission_adjustments', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('settlement_entries', 'updated_at'));
        $this->assertTrue(Schema::hasColumn('commissions', 'created_at'));
        $this->assertTrue(Schema::hasColumn('commission_adjustments', 'created_at'));
        $this->assertTrue(Schema::hasColumn('settlement_entries', 'created_at'));
    }

    #[Test]
    public function commission_persists_created_at_without_updated_at(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $commission = Commission::factory()->forOrder($order)->create();

        $this->assertNotNull($commission->created_at);
        $this->assertNull($commission->updated_at);
    }

    #[Test]
    public function commission_model_does_not_define_state_transition_methods(): void
    {
        foreach (['markPaid', 'cancel', 'reverse'] as $method) {
            $this->assertFalse(
                method_exists(Commission::class, $method),
                "Commission must not define {$method}() — ledger transitions belong in CommissionService",
            );
        }
    }

    #[Test]
    public function append_only_models_do_not_use_update_or_delete_traits(): void
    {
        foreach ($this->appendOnlyModels as $modelClass) {
            $traits = class_uses_recursive($modelClass);

            $this->assertNotContains(
                SoftDeletes::class,
                $traits,
                "{$modelClass} must not use SoftDeletes",
            );
            $this->assertNotContains(
                HasOptimisticLock::class,
                $traits,
                "{$modelClass} must not use HasOptimisticLock",
            );
        }
    }

    #[Test]
    public function append_only_models_do_not_define_update_helper_methods(): void
    {
        $forbiddenMethodPatterns = [
            'markPaid',
            'markInvoiced',
            'cancel',
            'reverse',
            'updateWithVersion',
        ];

        foreach ($this->appendOnlyModels as $modelClass) {
            $reflection = new ReflectionClass($modelClass);

            foreach ($reflection->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $modelClass) {
                    continue;
                }

                $this->assertNotContains(
                    $method->getName(),
                    $forbiddenMethodPatterns,
                    "{$modelClass} must not define update helper {$method->getName()}",
                );
            }

            $this->assertTrue(true);
        }
    }
}
