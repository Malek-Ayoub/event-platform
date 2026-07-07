<?php

namespace App\Models;

use App\Enums\EventDomain\SeatingUnitStatus;
use App\Support\Concerns\BelongsToVenue;
use Database\Factories\VenueTableFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property int $venue_id
 * @property int $event_id
 * @property int $zone_id
 * @property SeatingUnitStatus $status
 */
class VenueTable extends Model
{
    /** @use HasFactory<VenueTableFactory> */
    use BelongsToVenue, HasFactory;

    protected $table = 'venue_tables';

    protected $fillable = [
        'venue_id',
        'event_id',
        'zone_id',
        'table_number',
        'capacity',
        'min_spend',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'event_id' => 'integer',
            'zone_id' => 'integer',
            'table_number' => 'string',
            'capacity' => 'integer',
            'min_spend' => 'decimal:2',
            'status' => SeatingUnitStatus::class,
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

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function tableSeats(): HasMany
    {
        return $this->hasMany(TableSeat::class, 'venue_table_id');
    }

    public function reservations(): HasManyThrough
    {
        return $this->hasManyThrough(
            Reservation::class,
            TableSeat::class,
            'venue_table_id',
            'table_seat_id',
            'id',
            'id',
        );
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', SeatingUnitStatus::Available);
    }
}
