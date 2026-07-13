<?php

namespace App\Models;

use Database\Factories\TicketSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ticket_id
 * @property array<string, mixed> $payload
 */
class TicketSnapshot extends Model
{
    /** @use HasFactory<TicketSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'payload' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
