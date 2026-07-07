<?php

namespace App\Models;

use App\Enums\EventDomain\SeatingUnitStatus;
use App\Support\Concerns\BelongsToVenue;
use Database\Factories\TableSeatFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $venue_id
 * @property int $venue_table_id
 * @property SeatingUnitStatus $status
 */
class TableSeat extends Model
{
    /** @use HasFactory<TableSeatFactory> */
    use BelongsToVenue, HasFactory;

    protected $fillable = [
        'venue_id',
        'venue_table_id',
        'seat_number',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'venue_table_id' => 'integer',
            'seat_number' => 'string',
            'status' => SeatingUnitStatus::class,
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function venueTable(): BelongsTo
    {
        return $this->belongsTo(VenueTable::class, 'venue_table_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'table_seat_id');
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
