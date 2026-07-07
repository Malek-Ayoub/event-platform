<?php

namespace App\Models;

use App\Enums\OrdersDomain\TicketStatus;
use App\Support\Concerns\BelongsToVenue;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $venue_id
 * @property int $event_id
 * @property int $order_id
 * @property int $ticket_type_id
 * @property string $serial
 * @property TicketStatus $status
 */
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use BelongsToVenue, HasFactory;

    protected $fillable = [
        'venue_id',
        'event_id',
        'order_id',
        'ticket_type_id',
        'serial',
        'qr_code_path',
        'status',
        'checked_in_at',
        'checked_in_by',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'event_id' => 'integer',
            'order_id' => 'integer',
            'ticket_type_id' => 'integer',
            'serial' => 'string',
            'qr_code_path' => 'string',
            'status' => TicketStatus::class,
            'checked_in_at' => 'datetime',
            'checked_in_by' => 'integer',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithStatus(Builder $query, TicketStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('status', TicketStatus::Valid);
    }
}
