<?php

namespace App\Models;

use App\Enums\CommerceDomain\DiscountType;
use App\Support\Concerns\BelongsToVenue;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $venue_id
 * @property string $code
 * @property DiscountType $discount_type
 * @property string $discount_value
 * @property bool $is_active
 */
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use BelongsToVenue, HasFactory, SoftDeletes;

    protected $fillable = [
        'venue_id',
        'code',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_uses',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'code' => 'string',
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
