<?php

namespace Tests\Unit\Models\FinancialDomain;

use App\Models\Commission;
use App\Models\CommissionAdjustment;
use App\Models\CommissionPayment;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\SettlementEntry;
use App\Support\Concerns\BelongsToVenue;
use App\Support\Concerns\HasOptimisticLock;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinancialDomainArchitectureTest extends TestCase
{
    /** @var list<class-string> */
    private array $financialModels = [
        PaymentTransaction::class,
        Refund::class,
        Commission::class,
        CommissionAdjustment::class,
        SettlementEntry::class,
        CommissionPayment::class,
    ];

    #[Test]
    public function financial_domain_models_use_belongs_to_venue(): void
    {
        foreach ($this->financialModels as $modelClass) {
            $this->assertContains(
                BelongsToVenue::class,
                class_uses_recursive($modelClass),
                "{$modelClass} should use BelongsToVenue",
            );
        }
    }

    #[Test]
    public function financial_domain_models_do_not_use_optimistic_lock(): void
    {
        foreach ($this->financialModels as $modelClass) {
            $this->assertNotContains(
                HasOptimisticLock::class,
                class_uses_recursive($modelClass),
                "{$modelClass} must not use HasOptimisticLock",
            );
        }
    }

    #[Test]
    public function financial_domain_models_do_not_use_soft_deletes(): void
    {
        foreach ($this->financialModels as $modelClass) {
            $this->assertNotContains(
                SoftDeletes::class,
                class_uses_recursive($modelClass),
                "{$modelClass} must not use SoftDeletes",
            );
        }
    }

    #[Test]
    public function ledger_models_are_created_at_only(): void
    {
        $this->assertNull(Commission::UPDATED_AT);
        $this->assertNull(CommissionAdjustment::UPDATED_AT);
        $this->assertNull(SettlementEntry::UPDATED_AT);
        $this->assertFalse(Schema::hasColumn('commissions', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('commission_adjustments', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('settlement_entries', 'updated_at'));
    }

    #[Test]
    public function order_payment_transaction_relation_is_has_many(): void
    {
        $this->assertInstanceOf(HasMany::class, (new Order)->paymentTransactions());
    }

    #[Test]
    public function order_commission_relation_is_has_one(): void
    {
        $this->assertInstanceOf(HasOne::class, (new Order)->commission());
    }

    #[Test]
    public function refund_commission_adjustment_relation_is_has_one(): void
    {
        $this->assertInstanceOf(HasOne::class, (new Refund)->commissionAdjustment());
    }

    #[Test]
    public function commission_is_an_ledger_model_without_state_transition_methods(): void
    {
        foreach (['markPaid', 'cancel', 'reverse'] as $method) {
            $this->assertFalse(
                method_exists(Commission::class, $method),
                "Commission must not define {$method}()",
            );
        }
    }

    #[Test]
    public function order_does_not_expose_refund_aggregate_method(): void
    {
        $this->assertFalse(
            method_exists(Order::class, 'refund'),
            'Refund is an independent aggregate — Order must not define refund()',
        );
    }
}
