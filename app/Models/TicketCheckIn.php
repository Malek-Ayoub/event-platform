<?php

namespace App\Models;

use Database\Factories\TicketCheckInFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable admission audit log — source of truth for ticket check-ins (Phase 8.4).
 *
 * @property int $id
 * @property int $ticket_id
 * @property Carbon $checked_in_at
 * @property int $checked_in_by_user_id
 * @property int|null $gate_id
 * @property string|null $device_id
 * @property string|null $notes
 * @property Carbon $created_at
 */
class TicketCheckIn extends Model
{
    /** @use HasFactory<TicketCheckInFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'ticket_id',
        'checked_in_at',
        'checked_in_by_user_id',
        'gate_id',
        'device_id',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'checked_in_at' => 'datetime',
            'checked_in_by_user_id' => 'integer',
            'gate_id' => 'integer',
            'device_id' => 'string',
            'notes' => 'string',
            'created_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by_user_id');
    }
}
