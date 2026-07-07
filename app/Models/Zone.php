<?php

namespace App\Models;

use App\Support\Concerns\BelongsToVenue;
use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $venue_id
 * @property int $event_id
 * @property string $name
 */
class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use BelongsToVenue, HasFactory;

    protected $fillable = [
        'venue_id',
        'event_id',
        'name',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'event_id' => 'integer',
            'name' => 'string',
            'description' => 'string',
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

    public function venueTables(): HasMany
    {
        return $this->hasMany(VenueTable::class);
    }
}
