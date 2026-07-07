<?php

namespace App\Models;

use App\Enums\InfrastructureDomain\WebhookLogStatus;
use Database\Factories\WebhookLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $provider
 * @property string $provider_event_id
 * @property string $payload
 * @property string|null $signature
 * @property WebhookLogStatus $status
 * @property string|null $error_message
 */
class WebhookLog extends Model
{
    /** @use HasFactory<WebhookLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'provider',
        'provider_event_id',
        'payload',
        'signature',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'provider' => 'string',
            'provider_event_id' => 'string',
            'payload' => 'string',
            'signature' => 'string',
            'status' => WebhookLogStatus::class,
            'error_message' => 'string',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithStatus(Builder $query, WebhookLogStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', WebhookLogStatus::Failed);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where('status', WebhookLogStatus::Processed);
    }
}
