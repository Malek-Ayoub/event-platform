<?php

namespace App\Models;

use App\Enums\InfrastructureDomain\MediaType;
use App\Support\Concerns\BelongsToVenue;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $venue_id
 * @property string $mediable_type
 * @property int $mediable_id
 * @property MediaType $type
 * @property string $url
 * @property int $sort_order
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use BelongsToVenue, HasFactory;

    protected $fillable = [
        'venue_id',
        'mediable_type',
        'mediable_id',
        'type',
        'url',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'mediable_type' => 'string',
            'mediable_id' => 'integer',
            'type' => MediaType::class,
            'url' => 'string',
            'sort_order' => 'integer',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithType(Builder $query, MediaType $type): Builder
    {
        return $query->where('type', $type);
    }
}
