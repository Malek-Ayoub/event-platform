<?php

namespace App\Models;

use App\Enums\FinancialDomain\RefundStatus;
use App\Support\Concerns\BelongsToVenue;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $venue_id
 * @property int $order_id
 * @property int|null $payment_transaction_id
 * @property RefundStatus $status
 */
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use BelongsToVenue, HasFactory;

    protected $fillable = [
        'venue_id',
        'order_id',
        'payment_transaction_id',
        'amount',
        'status',
        'reason',
        'provider_refund_id',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'order_id' => 'integer',
            'payment_transaction_id' => 'integer',
            'amount' => 'decimal:2',
            'status' => RefundStatus::class,
            'reason' => 'string',
            'provider_refund_id' => 'string',
            'processed_at' => 'datetime',
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

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function commissionAdjustment(): HasOne
    {
        return $this->hasOne(CommissionAdjustment::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithStatus(Builder $query, RefundStatus $status): Builder
    {
        return $query->where('status', $status);
    }
}
