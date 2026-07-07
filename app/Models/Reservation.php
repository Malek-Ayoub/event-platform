<?php

namespace App\Models;

use App\Enums\EventDomain\ReservationStatus;
use App\Support\Concerns\BelongsToVenue;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $venue_id
 * @property int $table_seat_id
 * @property int|null $order_id
 * @property ReservationStatus $status
 */
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use BelongsToVenue, HasFactory;

    protected $fillable = [
        'venue_id',
        'table_seat_id',
        'order_id',
        'customer_name',
        'customer_phone',
        'status',
        'held_until',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'table_seat_id' => 'integer',
            'order_id' => 'integer',
            'customer_name' => 'string',
            'customer_phone' => 'string',
            'status' => ReservationStatus::class,
            'held_until' => 'datetime',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function tableSeat(): BelongsTo
    {
        return $this->belongsTo(TableSeat::class, 'table_seat_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ReservationStatus::Hold,
            ReservationStatus::Confirmed,
        ]);
    }
}
