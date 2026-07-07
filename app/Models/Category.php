<?php

namespace App\Models;

use App\Support\Concerns\BelongsToVenue;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $venue_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use BelongsToVenue, HasFactory;

    protected $fillable = [
        'venue_id',
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'name' => 'string',
            'slug' => 'string',
            'description' => 'string',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
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
