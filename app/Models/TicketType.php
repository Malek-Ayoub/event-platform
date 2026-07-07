<?php

namespace App\Models;

use App\Support\Concerns\BelongsToVenue;
use App\Support\Concerns\HasOptimisticLock;
use Database\Factories\TicketTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $venue_id
 * @property int $event_id
 * @property string $name
 * @property string $price
 * @property int $quantity
 * @property int $quantity_sold
 * @property int $version
 */
class TicketType extends Model
{
    /** @use HasFactory<TicketTypeFactory> */
    use BelongsToVenue, HasFactory, HasOptimisticLock;

    protected $fillable = [
        'venue_id',
        'event_id',
        'name',
        'price',
        'quantity',
        'quantity_sold',
        'sale_start',
        'sale_end',
        'benefits',
        'color',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'event_id' => 'integer',
            'name' => 'string',
            'price' => 'decimal:2',
            'quantity' => 'integer',
            'quantity_sold' => 'integer',
            'sale_start' => 'datetime',
            'sale_end' => 'datetime',
            'benefits' => 'array',
            'color' => 'string',
            'version' => 'integer',
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

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
