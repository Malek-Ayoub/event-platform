<?php

namespace App\Models;

use App\Enums\FinancialDomain\CommissionStatus;
use App\Support\Concerns\BelongsToVenue;
use Database\Factories\CommissionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ledger entry for platform commission on an order — not a payment, refund, or invoice.
 *
 * @property int $id
 * @property int $venue_id
 * @property int $order_id
 * @property CommissionStatus $status
 */
class Commission extends Model
{
    /** @use HasFactory<CommissionFactory> */
    use BelongsToVenue, HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'venue_id',
        'order_id',
        'amount',
        'rate',
        'status',
        'payout_reference',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'order_id' => 'integer',
            'amount' => 'decimal:2',
            'rate' => 'decimal:2',
            'status' => CommissionStatus::class,
            'payout_reference' => 'string',
            'paid_at' => 'datetime',
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

    public function adjustments(): HasMany
    {
        return $this->hasMany(CommissionAdjustment::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithStatus(Builder $query, CommissionStatus $status): Builder
    {
        return $query->where('status', $status);
    }
}
