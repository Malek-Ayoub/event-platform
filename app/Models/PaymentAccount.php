<?php

namespace App\Models;

use App\Enums\Payments\PaymentWalletProvider;
use App\Services\Payments\PaymentAccountGuard;
use App\Support\Concerns\BelongsToVenue;
use Database\Factories\PaymentAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reusable merchant wallet credentials (venue-scoped).
 *
 * Linked to events via {@see EventPaymentAccount}. The same wallet can be
 * attached to many events without duplicating credentials.
 *
 * @property int $id
 * @property int $venue_id
 * @property PaymentWalletProvider $provider
 * @property string $account_identifier
 * @property string|null $cash_code
 * @property string|null $currency
 * @property string $display_name
 */
class PaymentAccount extends Model
{
    /** @use HasFactory<PaymentAccountFactory> */
    use BelongsToVenue, HasFactory;

    protected $fillable = [
        'venue_id',
        'provider',
        'account_identifier',
        'cash_code',
        'currency',
        'display_name',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'provider' => PaymentWalletProvider::class,
            'account_identifier' => 'string',
            'cash_code' => 'string',
            'currency' => 'string',
            'display_name' => 'string',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function eventPaymentAccounts(): HasMany
    {
        return $this->hasMany(EventPaymentAccount::class);
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_payment_accounts')
            ->withPivot(['is_default', 'is_active'])
            ->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    protected static function booted(): void
    {
        static::updating(function (PaymentAccount $account): void {
            if ($account->isDirty(['provider', 'account_identifier'])) {
                app(PaymentAccountGuard::class)->assertCredentialsMutable($account);
            }
        });

        static::deleting(function (PaymentAccount $account): void {
            app(PaymentAccountGuard::class)->assertCredentialsMutable($account);
        });
    }
}
