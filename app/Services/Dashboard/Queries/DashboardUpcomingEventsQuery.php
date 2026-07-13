<?php

namespace App\Services\Dashboard\Queries;

use App\Enums\EventDomain\EventStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardUpcomingEventsQuery
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $venueId, int $limit = 5, ?Carbon $asOf = null): array
    {
        $moment = ($asOf ?? now())->copy();

        $capacityTotals = DB::table('ticket_types')
            ->select('event_id')
            ->selectRaw('COALESCE(SUM(quantity), 0) as capacity')
            ->selectRaw('COALESCE(SUM(quantity_sold), 0) as tickets_sold')
            ->where('venue_id', $venueId)
            ->groupBy('event_id');

        $rows = DB::table('events')
            ->leftJoinSub($capacityTotals, 'capacity_totals', 'events.id', '=', 'capacity_totals.event_id')
            ->where('events.venue_id', $venueId)
            ->whereNull('events.deleted_at')
            ->where('events.start_datetime', '>=', $moment)
            ->whereNotIn('events.status', [EventStatus::Cancelled->value, EventStatus::Completed->value])
            ->orderBy('events.start_datetime')
            ->orderBy('events.id')
            ->limit($limit)
            ->get([
                'events.id',
                'events.name',
                'events.start_datetime',
                'events.status',
                'capacity_totals.capacity',
                'capacity_totals.tickets_sold',
            ]);

        return $rows->map(function (object $row): array {
            $capacity = (int) ($row->capacity ?? 0);
            $ticketsSold = (int) ($row->tickets_sold ?? 0);

            return [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'starts_at' => Carbon::parse((string) $row->start_datetime)->toIso8601String(),
                'tickets_sold' => $ticketsSold,
                'capacity' => $capacity,
                'remaining' => max(0, $capacity - $ticketsSold),
                'status' => (string) $row->status,
            ];
        })->all();
    }
}
