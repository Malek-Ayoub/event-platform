<?php

namespace App\Models;

use App\Enums\FinancialDomain\CommissionPaymentMethod;
use App\Support\Concerns\BelongsToVenue;
use Database\Factories\CommissionPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable record of commission received from an organizer outside the platform (Phase 8.5.3).
 *
 * @property int $id
 * @property int $venue_id
 * @property int|null $payment_account_id
 * @property string $amount
 * @property string $currency
 * @property CommissionPaymentMethod $payment_method
 * @property string|null $reference_number
 * @property \Illuminate\Support\Carbon $received_at
 * @property int $received_by_user_id
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 */
class CommissionPayment extends Model
{
    /** @use HasFactory<CommissionPaymentFactory> */
    use BelongsToVenue, HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'venue_id',
        'payment_account_id',
        'amount',
        'currency',
        'payment_method',
        'reference_number',
        'received_at',
        'received_by_user_id',
        'notes',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'payment_account_id' => 'integer',
            'amount' => 'decimal:2',
            'currency' => 'string',
            'payment_method' => CommissionPaymentMethod::class,
            'reference_number' => 'string',
            'received_at' => 'datetime',
            'received_by_user_id' => 'integer',
            'notes' => 'string',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }
}
