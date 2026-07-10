<?php

namespace App\Models;

use App\Support\Concerns\BelongsToVenue;
use Database\Factories\TicketNumberCounterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $venue_id
 * @property int $event_id
 * @property int $last_number
 */
class TicketNumberCounter extends Model
{
    /** @use HasFactory<TicketNumberCounterFactory> */
    use BelongsToVenue, HasFactory;

    protected $fillable = [
        'venue_id',
        'event_id',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'event_id' => 'integer',
            'last_number' => 'integer',
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
}
