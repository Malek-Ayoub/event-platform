<?php

namespace App\Models;

use App\Services\Payments\PaymentAccountGuard;
use Database\Factories\EventPaymentAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links an event to a reusable {@see PaymentAccount}.
 *
 * @property int $id
 * @property int $event_id
 * @property int $payment_account_id
 * @property bool $is_default
 * @property bool $is_active
 */
class EventPaymentAccount extends Model
{
    /** @use HasFactory<EventPaymentAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'payment_account_id',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'event_id' => 'integer',
            'payment_account_id' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    protected static function booted(): void
    {
        static::deleting(function (EventPaymentAccount $link): void {
            app(PaymentAccountGuard::class)->assertCanUnlinkFromEvent($link);
        });
    }
}
