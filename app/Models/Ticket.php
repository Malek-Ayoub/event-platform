<?php

namespace App\Models;

use App\Enums\OrdersDomain\TicketStatus;
use App\Support\Concerns\BelongsToVenue;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $venue_id
 * @property int $event_id
 * @property int $order_id
 * @property int $ticket_type_id
 * @property string $serial
 * @property string $ticket_number
 * @property string $qr_token
 * @property Carbon|null $issued_at
 * @property TicketStatus $status
 * @property Carbon|null $checked_in_at Denormalized cache of the latest check-in (see ticket_check_ins).
 * @property int|null $checked_in_by Denormalized cache of the staff user for the latest check-in.
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
        'ticket_number',
        'qr_token',
        'issued_at',
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
            'ticket_number' => 'string',
            'qr_token' => 'string',
            'issued_at' => 'datetime',
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

    public function snapshot(): HasOne
    {
        return $this->hasOne(TicketSnapshot::class);
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(TicketArtifact::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(TicketCheckIn::class);
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
    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('status', TicketStatus::Issued);
    }
}
