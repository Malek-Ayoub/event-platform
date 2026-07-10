<?php

namespace App\Models;

use App\Support\Concerns\BelongsToVenue;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $venue_id
 * @property int $order_id
 * @property int $ticket_type_id
 * @property int $quantity
 * @property string $unit_price
 */
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use BelongsToVenue, HasFactory;

    protected $fillable = [
        'venue_id',
        'order_id',
        'ticket_type_id',
        'quantity',
        'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'order_id' => 'integer',
            'ticket_type_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }
}
