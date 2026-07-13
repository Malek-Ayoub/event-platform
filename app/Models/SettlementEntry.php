<?php

namespace App\Models;

use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Support\Concerns\BelongsToVenue;
use Database\Factories\SettlementEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable platform commission receivable ledger row (Phase 8.5.1).
 *
 * Tracks commission due, adjustments, and manual payments — not organizer ticket revenue.
 *
 * @property int $id
 * @property int $venue_id
 * @property int $event_id
 * @property int|null $payment_transaction_id
 * @property int $order_id
 * @property SettlementEntryType $type
 * @property SettlementEntryDirection $direction
 * @property string $amount
 * @property string $currency
 * @property string $reference_type
 * @property int $reference_id
 * @property string $balance_after Outstanding commission owed by the organizer to the platform
 * @property string|null $correlation_id
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property \Illuminate\Support\Carbon $created_at
 */
class SettlementEntry extends Model
{
    /** @use HasFactory<SettlementEntryFactory> */
    use BelongsToVenue, HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'venue_id',
        'event_id',
        'payment_transaction_id',
        'order_id',
        'type',
        'direction',
        'amount',
        'currency',
        'reference_type',
        'reference_id',
        'balance_after',
        'correlation_id',
        'metadata',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'event_id' => 'integer',
            'payment_transaction_id' => 'integer',
            'order_id' => 'integer',
            'type' => SettlementEntryType::class,
            'direction' => SettlementEntryDirection::class,
            'amount' => 'decimal:2',
            'currency' => 'string',
            'reference_type' => 'string',
            'reference_id' => 'integer',
            'balance_after' => 'decimal:2',
            'correlation_id' => 'string',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
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

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
