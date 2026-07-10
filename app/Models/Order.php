<?php

namespace App\Models;

use App\Enums\OrdersDomain\OrderStatus;
use App\Support\Concerns\BelongsToVenue;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $venue_id
 * @property int $event_id
 * @property int|null $payment_account_id
 * @property int|null $customer_user_id
 * @property string $order_number
 * @property OrderStatus $status
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use BelongsToVenue, HasFactory;

    protected $fillable = [
        'venue_id',
        'event_id',
        'payment_account_id',
        'customer_user_id',
        'order_number',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'commission_amount',
        'coupon_id',
        'promo_code_id',
        'payment_method',
        'payment_reference',
        'status',
        'customer_name',
        'customer_email',
        'customer_phone',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'event_id' => 'integer',
            'payment_account_id' => 'integer',
            'customer_user_id' => 'integer',
            'order_number' => 'string',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'coupon_id' => 'integer',
            'promo_code_id' => 'integer',
            'payment_method' => 'string',
            'payment_reference' => 'string',
            'status' => OrderStatus::class,
            'customer_name' => 'string',
            'customer_email' => 'string',
            'customer_phone' => 'string',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithStatus(Builder $query, OrderStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::Paid);
    }
}
