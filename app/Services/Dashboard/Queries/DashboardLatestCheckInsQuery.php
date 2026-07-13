<?php

namespace App\Services\Dashboard\Queries;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardLatestCheckInsQuery
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $venueId, int $limit = 5): array
    {
        $rows = DB::table('ticket_check_ins')
            ->select([
                'ticket_check_ins.checked_in_at',
                'ticket_check_ins.gate_id',
                'ticket_check_ins.device_id',
                'tickets.ticket_number',
                'orders.customer_name',
            ])
            ->join('tickets', 'tickets.id', '=', 'ticket_check_ins.ticket_id')
            ->leftJoin('orders', 'orders.id', '=', 'tickets.order_id')
            ->where('tickets.venue_id', $venueId)
            ->orderByDesc('ticket_check_ins.checked_in_at')
            ->orderByDesc('ticket_check_ins.id')
            ->limit($limit)
            ->get();

        return $rows->map(fn (object $row): array => [
            'ticket_number' => (string) $row->ticket_number,
            'holder_name' => (string) ($row->customer_name ?? ''),
            'checked_in_at' => $row->checked_in_at !== null
                ? (string) Carbon::parse((string) $row->checked_in_at)->toIso8601String()
                : null,
            'gate' => $this->resolveGateLabel($row),
        ])->all();
    }

    private function resolveGateLabel(object $row): ?string
    {
        if ($row->gate_id !== null) {
            return 'Gate '.$row->gate_id;
        }

        if ($row->device_id !== null && $row->device_id !== '') {
            return (string) $row->device_id;
        }

        return null;
    }
}
