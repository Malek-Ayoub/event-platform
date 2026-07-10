<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $outbox_event_id
 * @property string $consumer_key
 * @property Carbon $processed_at
 * @property Carbon $created_at
 */
class OutboxConsumerReceipt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'outbox_event_id',
        'consumer_key',
        'processed_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'outbox_event_id' => 'integer',
            'consumer_key' => 'string',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function outboxEvent(): BelongsTo
    {
        return $this->belongsTo(OutboxEvent::class);
    }
}
