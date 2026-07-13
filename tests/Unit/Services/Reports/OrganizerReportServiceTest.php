<?php

namespace Tests\Unit\Services\Reports;

use App\Enums\FinancialDomain\CommissionPaymentMethod;
use App\Enums\FinancialDomain\RefundStatus;
use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Enums\OrdersDomain\OrderStatus;
use App\Enums\OrdersDomain\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Commissions\CommissionPaymentService;
use App\Services\Commissions\Data\RecordCommissionPaymentData;
use App\Services\Reports\Data\OrganizerReportFilter;
use App\Services\Reports\OrganizerReportService;
use App\Services\Settlements\Data\AppendSettlementEntryData;
use App\Services\Settlements\Data\SettlementDateRange;
use App\Services\Settlements\SettlementEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganizerReportServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_builds_organizer_report_from_operational_and_settlement_sources(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '200.00',
            'status' => OrderStatus::Paid,
            'updated_at' => Carbon::parse('2026-01-15 12:00:00'),
        ]);

        Ticket::factory()->forOrder($order)->create([
            'status' => TicketStatus::Issued,
            'issued_at' => Carbon::parse('2026-01-15 12:00:00'),
        ]);

        Ticket::factory()->forOrder($order)->create([
            'status' => TicketStatus::CheckedIn,
            'issued_at' => Carbon::parse('2026-01-15 12:00:00'),
            'checked_in_at' => Carbon::parse('2026-01-15 18:00:00'),
        ]);

        Refund::factory()->create([
            'venue_id' => $venue->id,
            'order_id' => $order->id,
            'amount' => '25.00',
            'status' => RefundStatus::Processed,
            'processed_at' => Carbon::parse('2026-01-16 10:00:00'),
        ]);

        app(SettlementEntryService::class)->append(new AppendSettlementEntryData(
            venueId: $venue->id,
            eventId: $event->id,
            orderId: $order->id,
            type: SettlementEntryType::CommissionDue,
            direction: SettlementEntryDirection::Credit,
            amount: '20.00',
            currency: 'USD',
            referenceType: 'commission',
            referenceId: 1,
            occurredAt: Carbon::parse('2026-01-15 12:00:00'),
        ));

        $admin = User::factory()->superAdmin()->create();
        app(CommissionPaymentService::class)->recordPayment(new RecordCommissionPaymentData(
            venueId: $venue->id,
            amount: '8.00',
            currency: 'USD',
            paymentMethod: CommissionPaymentMethod::Cash,
            receivedAt: Carbon::parse('2026-01-17 09:00:00'),
            receivedBy: $admin,
        ));

        $range = new SettlementDateRange(
            from: Carbon::parse('2026-01-01')->startOfDay(),
            to: Carbon::parse('2026-01-31')->endOfDay(),
        );

        $report = app(OrganizerReportService::class)->build(new OrganizerReportFilter(
            venueId: $venue->id,
            range: $range,
        ));

        $this->assertSame('200.00', $report->sales['gross_sales']);
        $this->assertSame(1, $report->sales['orders_count']);
        $this->assertSame(2, $report->sales['tickets_sold']);
        $this->assertSame('200.00', $report->sales['average_order_value']);
        $this->assertSame('200.00', $report->revenue['gross_revenue']);
        $this->assertSame('25.00', $report->revenue['refunded_amount']);
        $this->assertSame('175.00', $report->revenue['net_revenue']);
        $this->assertSame(2, $report->attendance['tickets_issued']);
        $this->assertSame(1, $report->attendance['checked_in']);
        $this->assertSame('50.00', $report->attendance['attendance_rate']);
        $this->assertSame('20.00', $report->commission['commission_due']);
        $this->assertSame('8.00', $report->commission['commission_paid']);
        $this->assertSame('12.00', $report->commission['outstanding_commission']);
        $this->assertSame('USD', $report->meta['currency']);
    }

    #[Test]
    public function it_filters_report_metrics_by_event_id(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $includedEvent = Event::factory()->create(['venue_id' => $venue->id]);
        $excludedEvent = Event::factory()->create(['venue_id' => $venue->id]);

        $includedOrder = Order::factory()->forEvent($includedEvent)->create([
            'total' => '100.00',
            'status' => OrderStatus::Paid,
            'updated_at' => now(),
        ]);

        Order::factory()->forEvent($excludedEvent)->create([
            'total' => '500.00',
            'status' => OrderStatus::Paid,
            'updated_at' => now(),
        ]);

        Ticket::factory()->forOrder($includedOrder)->create([
            'status' => TicketStatus::CheckedIn,
            'issued_at' => now(),
            'checked_in_at' => now(),
        ]);

        app(SettlementEntryService::class)->append(new AppendSettlementEntryData(
            venueId: $venue->id,
            eventId: $includedEvent->id,
            orderId: $includedOrder->id,
            type: SettlementEntryType::CommissionDue,
            direction: SettlementEntryDirection::Credit,
            amount: '10.00',
            currency: 'USD',
            referenceType: 'commission',
            referenceId: 2,
            occurredAt: now(),
        ));

        $report = app(OrganizerReportService::class)->build(new OrganizerReportFilter(
            venueId: $venue->id,
            range: new SettlementDateRange,
            eventId: $includedEvent->id,
        ));

        $this->assertSame('100.00', $report->sales['gross_sales']);
        $this->assertSame(1, $report->sales['orders_count']);
        $this->assertSame(1, $report->sales['tickets_sold']);
        $this->assertSame('10.00', $report->commission['commission_due']);
        $this->assertSame('0.00', $report->commission['commission_paid']);
        $this->assertSame('10.00', $report->commission['outstanding_commission']);
        $this->assertSame($includedEvent->id, $report->meta['event_id']);
    }

    #[Test]
    public function it_excludes_metrics_outside_the_requested_date_range(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '80.00',
            'status' => OrderStatus::Paid,
            'updated_at' => Carbon::parse('2026-02-10 12:00:00'),
        ]);

        Ticket::factory()->forOrder($order)->create([
            'status' => TicketStatus::Issued,
            'issued_at' => Carbon::parse('2026-02-10 12:00:00'),
        ]);

        $januaryReport = app(OrganizerReportService::class)->build(new OrganizerReportFilter(
            venueId: $venue->id,
            range: new SettlementDateRange(
                from: Carbon::parse('2026-01-01')->startOfDay(),
                to: Carbon::parse('2026-01-31')->endOfDay(),
            ),
        ));

        $this->assertSame('0.00', $januaryReport->sales['gross_sales']);
        $this->assertSame(0, $januaryReport->sales['orders_count']);
        $this->assertSame(0, $januaryReport->sales['tickets_sold']);
    }
}
