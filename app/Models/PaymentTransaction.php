<?php

namespace App\Models;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Support\Concerns\BelongsToVenue;
use Database\Factories\PaymentTransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $venue_id
 * @property int $order_id
 * @property PaymentTransactionStatus $status
 * @property string|null $transaction_number Customer-submitted transaction number (Manual Wallet Transfer, §7.9)
 * @property \Illuminate\Support\Carbon|null $expires_at Payment instruction expiry (Manual Wallet Transfer, §7.9)
 */
class PaymentTransaction extends Model
{
    /** @use HasFactory<PaymentTransactionFactory> */
    use BelongsToVenue, HasFactory;

    protected $fillable = [
        'venue_id',
        'order_id',
        'provider',
        'provider_transaction_id',
        'transaction_number',
        'amount',
        'currency',
        'status',
        'expires_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'order_id' => 'integer',
            'provider' => 'string',
            'provider_transaction_id' => 'string',
            'transaction_number' => 'string',
            'amount' => 'decimal:2',
            'currency' => 'string',
            'status' => PaymentTransactionStatus::class,
            'expires_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithStatus(Builder $query, PaymentTransactionStatus $status): Builder
    {
        return $query->where('status', $status);
    }
}
