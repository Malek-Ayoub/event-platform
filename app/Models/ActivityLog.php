<?php

namespace App\Models;

use App\Support\Concerns\BelongsToVenue;
use Database\Factories\ActivityLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int|null $venue_id
 * @property int|null $actor_user_id
 * @property string $entity_type
 * @property int $entity_id
 * @property string $action
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property list<string>|null $changed_fields
 * @property string|null $ip_address
 */
class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use BelongsToVenue, HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'venue_id',
        'actor_user_id',
        'entity_type',
        'entity_id',
        'action',
        'old_values',
        'new_values',
        'changed_fields',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'actor_user_id' => 'integer',
            'entity_type' => 'string',
            'entity_id' => 'integer',
            'action' => 'string',
            'old_values' => 'array',
            'new_values' => 'array',
            'changed_fields' => 'array',
            'ip_address' => 'string',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
