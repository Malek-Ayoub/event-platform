<?php

namespace Tests\Unit\Models\FinancialDomain;

use App\Enums\FinancialDomain\CommissionStatus;
use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\FinancialDomain\RefundStatus;
use App\Models\Commission;
use App\Models\Event;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinancialDomainCastsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function payment_transaction_casts_status_enum_decimal_amount_and_payload_array(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $transaction = PaymentTransaction::factory()->forOrder($order)->create([
            'status' => PaymentTransactionStatus::Completed,
            'amount' => '250.75',
            'payload' => ['webhook' => 'received'],
        ]);

        $this->assertSame(PaymentTransactionStatus::Completed, $transaction->status);
        $this->assertSame('250.75', $transaction->amount);
        $this->assertSame(['webhook' => 'received'], $transaction->payload);
    }

    #[Test]
    public function refund_casts_status_enum_decimal_amount_and_processed_at_datetime(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $processedAt = now()->startOfSecond();
        $refund = Refund::factory()->forOrder($order)->create([
            'status' => RefundStatus::Processed,
            'amount' => '99.99',
            'processed_at' => $processedAt,
        ]);

        $this->assertSame(RefundStatus::Processed, $refund->status);
        $this->assertSame('99.99', $refund->amount);
        $this->assertTrue($refund->processed_at->equalTo($processedAt));
    }

    #[Test]
    public function commission_casts_status_enum_and_decimal_amounts(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $commission = Commission::factory()->forOrder($order)->create([
            'status' => CommissionStatus::Pending,
            'amount' => '12.50',
            'rate' => '5.00',
        ]);

        $this->assertSame(CommissionStatus::Pending, $commission->status);
        $this->assertSame('12.50', $commission->amount);
        $this->assertSame('5.00', $commission->rate);
    }
}
