<?php

namespace App\Models;

use App\Enums\EventDomain\EventStatus;
use App\Support\Concerns\BelongsToVenue;
use App\Support\Concerns\HasOptimisticLock;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $venue_id
 * @property int|null $category_id
 * @property string $name
 * @property string $slug
 * @property EventStatus $status
 * @property int $version
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use BelongsToVenue, HasFactory, HasOptimisticLock, SoftDeletes;

    protected $fillable = [
        'venue_id',
        'category_id',
        'name',
        'slug',
        'description',
        'banner_url',
        'gallery',
        'video_url',
        'dj_info',
        'start_datetime',
        'end_datetime',
        'status',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'category_id' => 'integer',
            'name' => 'string',
            'slug' => 'string',
            'description' => 'string',
            'banner_url' => 'string',
            'gallery' => 'array',
            'video_url' => 'string',
            'dj_info' => 'array',
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'status' => EventStatus::class,
            'version' => 'integer',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', EventStatus::Published);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', EventStatus::Draft);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithStatus(Builder $query, EventStatus $status): Builder
    {
        return $query->where('status', $status);
    }
}
