<?php

namespace App\Models;

use App\Support\Concerns\BelongsToVenue;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int|null $venue_id
 * @property int $user_id
 * @property string $type
 * @property array<string, mixed> $data
 * @property Carbon|null $read_at
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use BelongsToVenue, HasFactory, HasUuids;

    protected $fillable = [
        'venue_id',
        'user_id',
        'type',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'user_id' => 'integer',
            'type' => 'string',
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
