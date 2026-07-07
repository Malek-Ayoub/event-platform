<?php

namespace App\Models;

use App\Support\Concerns\BelongsToVenue;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $venue_id
 * @property int|null $event_id
 * @property string $name
 * @property string $price
 * @property bool $is_active
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToVenue, HasFactory;

    protected $fillable = [
        'venue_id',
        'event_id',
        'name',
        'description',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'event_id' => 'integer',
            'name' => 'string',
            'description' => 'string',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
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

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
