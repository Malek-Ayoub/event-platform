<?php

namespace Tests\Unit\Services\Payments;

use App\Enums\Payments\PaymentWalletProvider;
use App\Exceptions\Payments\PaymentAccountLockedException;
use App\Models\Event;
use App\Models\EventPaymentAccount;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Services\Payments\PaymentAccountGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentAccountGuardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_allows_credential_changes_when_no_orders_reference_account(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $account = PaymentAccount::factory()->forVenue($venue)->shamcash('WALLET-001')->create();

        app(PaymentAccountGuard::class)->assertCanUpdateCredentials(
            $account,
            PaymentWalletProvider::Syriatel,
            '0933123456',
        );

        $account->update([
            'provider' => PaymentWalletProvider::Syriatel,
            'account_identifier' => '0933123456',
        ]);

        $this->assertSame(PaymentWalletProvider::Syriatel, $account->fresh()->provider);
    }

    #[Test]
    public function it_blocks_credential_changes_when_orders_reference_account(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $account = PaymentAccount::factory()->forVenue($venue)->shamcash('WALLET-LOCKED')->create();
        EventPaymentAccount::factory()->forEvent($event)->forPaymentAccount($account)->create();

        Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'payment_account_id' => $account->id,
        ]);

        $this->expectException(PaymentAccountLockedException::class);

        $account->update(['account_identifier' => 'WALLET-CHANGED']);
    }

    #[Test]
    public function it_blocks_unlinking_event_account_when_event_has_orders(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $account = PaymentAccount::factory()->forVenue($venue)->shamcash('WALLET-001')->create();
        $link = EventPaymentAccount::factory()->forEvent($event)->forPaymentAccount($account)->create();

        Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'payment_account_id' => $account->id,
        ]);

        $this->expectException(PaymentAccountLockedException::class);

        $link->delete();
    }

    #[Test]
    public function it_allows_adding_new_default_account_when_event_already_has_orders(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $oldAccount = PaymentAccount::factory()->forVenue($venue)->shamcash('WALLET-OLD')->create();
        $oldLink = EventPaymentAccount::factory()->forEvent($event)->forPaymentAccount($oldAccount)->create();

        Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'payment_account_id' => $oldAccount->id,
        ]);

        $newAccount = PaymentAccount::factory()->forVenue($venue)->shamcash('WALLET-NEW')->create();
        $newLink = EventPaymentAccount::factory()
            ->forEvent($event)
            ->forPaymentAccount($newAccount)
            ->default()
            ->create();

        $oldLink->update(['is_default' => false]);

        $this->assertNotNull($newLink->fresh());
        $this->assertTrue($newLink->is_default);
    }
}
