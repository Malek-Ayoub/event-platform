<?php

namespace App\Models;

use App\Support\Concerns\BelongsToVenue;
use Database\Factories\CommissionAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only ledger adjustment linked to a refund — never updated after creation.
 *
 * @property int $id
 * @property int $venue_id
 * @property int $commission_id
 * @property int $refund_id
 */
class CommissionAdjustment extends Model
{
    /** @use HasFactory<CommissionAdjustmentFactory> */
    use BelongsToVenue, HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'venue_id',
        'commission_id',
        'refund_id',
        'adjustment_amount',
        'rate_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'commission_id' => 'integer',
            'refund_id' => 'integer',
            'adjustment_amount' => 'decimal:2',
            'rate_snapshot' => 'decimal:2',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }
}
