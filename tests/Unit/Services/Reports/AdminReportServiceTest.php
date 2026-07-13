<?php

namespace Tests\Unit\Services\Reports;

use App\Enums\FinancialDomain\RefundStatus;
use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Venue;
use App\Services\Reports\AdminReportService;
use App\Services\Reports\Data\AdminReportFilter;
use App\Services\Settlements\Data\AppendSettlementEntryData;
use App\Services\Settlements\Data\SettlementDateRange;
use App\Services\Settlements\SettlementEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminReportServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_builds_platform_report_with_monthly_commissions_and_refunds(): void
    {
        $venue = Venue::factory()->create();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '200.00',
            'status' => OrderStatus::Paid,
            'updated_at' => Carbon::parse('2026-01-20 12:00:00'),
        ]);

        Refund::factory()->create([
            'venue_id' => $venue->id,
            'order_id' => $order->id,
            'amount' => '20.00',
            'status' => RefundStatus::Processed,
            'processed_at' => Carbon::parse('2026-01-21 10:00:00'),
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
            occurredAt: Carbon::parse('2026-01-20 12:00:00'),
        ));

        $report = app(AdminReportService::class)->build(new AdminReportFilter(
            range: new SettlementDateRange(
                from: Carbon::parse('2026-01-01')->startOfDay(),
                to: Carbon::parse('2026-01-31')->endOfDay(),
            ),
            limit: 5,
        ));

        $this->assertSame('200.00', $report->platform['gross_revenue']);
        $this->assertSame('180.00', $report->platform['net_revenue']);
        $this->assertSame('20.00', $report->commissions['commission_due']);
        $this->assertSame('20.00', $report->refunds['refunded_amount']);
        $this->assertSame('10.00', $report->refunds['refund_rate']);
        $this->assertNotEmpty($report->commissions['monthly']);
        $this->assertSame('2026-01', $report->commissions['monthly'][0]['month']);
        $this->assertSame(5, $report->meta['limit']);
    }

    #[Test]
    public function it_excludes_activity_outside_requested_date_range(): void
    {
        $venue = Venue::factory()->create();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        Order::factory()->forEvent($event)->create([
            'total' => '90.00',
            'status' => OrderStatus::Paid,
            'updated_at' => Carbon::parse('2026-03-01 12:00:00'),
        ]);

        $report = app(AdminReportService::class)->build(new AdminReportFilter(
            range: new SettlementDateRange(
                from: Carbon::parse('2026-01-01')->startOfDay(),
                to: Carbon::parse('2026-01-31')->endOfDay(),
            ),
        ));

        $this->assertSame('0.00', $report->platform['gross_revenue']);
        $this->assertSame(0, $report->platform['orders_count']);
    }
}
