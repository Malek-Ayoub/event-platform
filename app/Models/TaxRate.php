<?php

namespace App\Models;

use App\Support\Concerns\BelongsToVenue;
use App\Support\Concerns\HasOptimisticLock;
use Database\Factories\TaxRateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $venue_id
 * @property string $name
 * @property string $rate
 * @property bool $is_active
 * @property int $version
 */
class TaxRate extends Model
{
    /** @use HasFactory<TaxRateFactory> */
    use BelongsToVenue, HasFactory, HasOptimisticLock;

    protected $fillable = [
        'venue_id',
        'name',
        'rate',
        'is_active',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'name' => 'string',
            'rate' => 'decimal:4',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
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
