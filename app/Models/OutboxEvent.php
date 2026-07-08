<?php

namespace App\Models;

use App\Enums\InfrastructureDomain\OutboxEventStatus;
use App\Support\Concerns\BelongsToVenue;
use Database\Factories\OutboxEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $venue_id
 * @property string|null $correlation_id
 * @property string $event_type
 * @property string $aggregate_type
 * @property int $aggregate_id
 * @property array<string, mixed> $payload
 * @property OutboxEventStatus $status
 * @property int $attempts
 * @property Carbon|null $processed_at
 */
class OutboxEvent extends Model
{
    /** @use HasFactory<OutboxEventFactory> */
    use BelongsToVenue, HasFactory;

    protected $fillable = [
        'venue_id',
        'correlation_id',
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'status',
        'attempts',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'correlation_id' => 'string',
            'event_type' => 'string',
            'aggregate_type' => 'string',
            'aggregate_id' => 'integer',
            'payload' => 'array',
            'status' => OutboxEventStatus::class,
            'attempts' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function aggregate(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithStatus(Builder $query, OutboxEventStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', OutboxEventStatus::Pending);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', OutboxEventStatus::Failed);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeProcessed(Builder $query): Builder
    {
        return $query->whereNotNull('processed_at');
    }
}
