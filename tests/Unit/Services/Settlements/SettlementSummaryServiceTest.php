<?php

namespace Tests\Unit\Services\Settlements;

use App\Enums\FinancialDomain\CommissionPaymentMethod;
use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Enums\OrdersDomain\OrderStatus;
use App\Enums\OrdersDomain\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\SettlementEntry;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Commissions\CommissionPaymentService;
use App\Services\Commissions\Data\RecordCommissionPaymentData;
use App\Services\Settlements\Data\AppendSettlementEntryData;
use App\Services\Settlements\Data\SettlementDateRange;
use App\Services\Settlements\SettlementSummaryService;
use App\Services\Settlements\SettlementEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SettlementSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_calculates_outstanding_from_entry_sums_not_balance_after_snapshot(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '100.00',
            'status' => OrderStatus::Paid,
            'updated_at' => now(),
        ]);

        Ticket::factory()->forOrder($order)->create([
            'status' => TicketStatus::Issued,
            'issued_at' => now(),
        ]);

        $service = app(SettlementEntryService::class);
        $due = $service->append(new AppendSettlementEntryData(
            venueId: $venue->id,
            eventId: $event->id,
            orderId: $order->id,
            type: SettlementEntryType::CommissionDue,
            direction: SettlementEntryDirection::Credit,
            amount: '25.00',
            currency: 'USD',
            referenceType: 'commission',
            referenceId: 1,
            occurredAt: now(),
        ));

        $service->append(new AppendSettlementEntryData(
            venueId: $venue->id,
            eventId: $event->id,
            orderId: $order->id,
            type: SettlementEntryType::CommissionAdjustment,
            direction: SettlementEntryDirection::Debit,
            amount: '5.00',
            currency: 'USD',
            referenceType: 'commission_adjustment',
            referenceId: 1,
            occurredAt: now()->addMinute(),
        ));

        SettlementEntry::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->whereKey($due->id)
            ->update(['balance_after' => '999.99']);

        $admin = User::factory()->superAdmin()->create();
        app(CommissionPaymentService::class)->recordPayment(new RecordCommissionPaymentData(
            venueId: $venue->id,
            amount: '10.00',
            currency: 'USD',
            paymentMethod: CommissionPaymentMethod::Cash,
            receivedAt: now()->addMinutes(2),
            receivedBy: $admin,
        ));

        $summary = app(SettlementSummaryService::class)->summarize($venue->id, new SettlementDateRange);

        $this->assertSame('100.00', $summary->grossSales);
        $this->assertSame(1, $summary->ticketsSold);
        $this->assertSame('25.00', $summary->commissionDue);
        $this->assertSame('5.00', $summary->commissionAdjustments);
        $this->assertSame('10.00', $summary->commissionPaid);
        $this->assertSame('10.00', $summary->commissionOutstanding);
    }

    #[Test]
    public function it_applies_date_filters_to_period_metrics(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '50.00',
            'status' => OrderStatus::Paid,
            'updated_at' => Carbon::parse('2026-01-15 12:00:00'),
        ]);

        app(SettlementEntryService::class)->append(new AppendSettlementEntryData(
            venueId: $venue->id,
            eventId: $event->id,
            orderId: $order->id,
            type: SettlementEntryType::CommissionDue,
            direction: SettlementEntryDirection::Credit,
            amount: '5.00',
            currency: 'USD',
            referenceType: 'commission',
            referenceId: 10,
            occurredAt: Carbon::parse('2026-01-15 12:00:00'),
        ));

        $summary = app(SettlementSummaryService::class)->summarize(
            $venue->id,
            new SettlementDateRange(
                from: Carbon::parse('2026-01-01'),
                to: Carbon::parse('2026-01-31')->endOfDay(),
            ),
        );

        $this->assertSame('5.00', $summary->commissionDue);

        $outsideRange = app(SettlementSummaryService::class)->summarize(
            $venue->id,
            new SettlementDateRange(
                from: Carbon::parse('2026-02-01'),
                to: Carbon::parse('2026-02-28')->endOfDay(),
            ),
        );

        $this->assertSame('0.00', $outsideRange->commissionDue);
        $this->assertSame('5.00', $outsideRange->commissionOutstanding);
    }
}
