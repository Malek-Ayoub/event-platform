<?php

namespace App\Services\Reports\Queries;

use App\Enums\OrdersDomain\OrderStatus;
use App\Enums\OrdersDomain\TicketStatus;
use App\Services\Reports\Queries\Concerns\AppliesReportDateRange;
use App\Services\Settlements\Data\SettlementDateRange;
use Illuminate\Support\Facades\DB;

class TopEventsQuery
{
    use AppliesReportDateRange;

    /**
     * @return list<array<string, mixed>>
     */
    public function execute(SettlementDateRange $range, int $limit): array
    {
        $orderTotals = DB::table('orders')
            ->select('event_id')
            ->selectRaw('COALESCE(SUM(total), 0) as gross_sales')
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Refunded->value]);

        $this->applyDateRange($orderTotals, 'updated_at', $range);
        $orderTotals->groupBy('event_id');

        $ticketTotals = DB::table('tickets')
            ->select('event_id')
            ->selectRaw('COUNT(*) as tickets_sold')
            ->whereNotIn('status', [
                TicketStatus::Cancelled->value,
                TicketStatus::Invalidated->value,
            ]);

        $this->applyDateRange($ticketTotals, 'issued_at', $range);
        $ticketTotals->groupBy('event_id');

        $rows = DB::table('events')
            ->joinSub($orderTotals, 'order_totals', 'events.id', '=', 'order_totals.event_id')
            ->join('venues', 'venues.id', '=', 'events.venue_id')
            ->leftJoinSub($ticketTotals, 'ticket_totals', 'events.id', '=', 'ticket_totals.event_id')
            ->select([
                'events.id as event_id',
                'events.name as event_name',
                'events.venue_id',
                'venues.name as venue_name',
            ])
            ->selectRaw('COALESCE(order_totals.gross_sales, 0) as gross_sales')
            ->selectRaw('COALESCE(ticket_totals.tickets_sold, 0) as tickets_sold')
            ->orderByDesc('gross_sales')
            ->orderBy('events.id')
            ->limit($limit)
            ->get();

        return $rows->map(fn (object $row): array => [
            'event_id' => (int) $row->event_id,
            'event_name' => (string) $row->event_name,
            'venue_id' => (int) $row->venue_id,
            'venue_name' => (string) $row->venue_name,
            'gross_sales' => $this->formatAmount($row->gross_sales ?? 0),
            'tickets_sold' => (int) ($row->tickets_sold ?? 0),
        ])->all();
    }
}
