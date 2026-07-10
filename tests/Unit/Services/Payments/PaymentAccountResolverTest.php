<?php

namespace Tests\Unit\Services\Payments;

use App\Exceptions\Payments\PaymentAccountNotFoundException;
use App\Models\Event;
use App\Models\EventPaymentAccount;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Services\Payments\PaymentAccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentAccountResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_resolves_default_active_account_from_order_event(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $account = PaymentAccount::factory()->forVenue($venue)->shamcash('WALLET-RESOLVE')->create();
        EventPaymentAccount::factory()->forEvent($event)->forPaymentAccount($account)->create();

        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'payment_account_id' => $account->id,
        ]);

        $resolved = app(PaymentAccountResolver::class)->resolveForOrder($order);

        $this->assertSame($account->id, $resolved->id);
    }

    #[Test]
    public function it_prefers_order_payment_account_snapshot_over_event_default(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $oldAccount = PaymentAccount::factory()->forVenue($venue)->shamcash('WALLET-OLD')->create();
        $newAccount = PaymentAccount::factory()->forVenue($venue)->shamcash('WALLET-NEW')->create();

        EventPaymentAccount::factory()->forEvent($event)->forPaymentAccount($newAccount)->create();

        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'payment_account_id' => $oldAccount->id,
        ]);

        $resolved = app(PaymentAccountResolver::class)->resolveForOrder($order);

        $this->assertSame($oldAccount->id, $resolved->id);
    }

    #[Test]
    public function it_throws_when_event_has_no_default_account(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create(['venue_id' => $venue->id]);

        $this->expectException(PaymentAccountNotFoundException::class);

        app(PaymentAccountResolver::class)->resolveForOrder($order);
    }

    #[Test]
    public function it_does_not_resolve_accounts_from_other_events(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $eventA = Event::factory()->create(['venue_id' => $venue->id]);
        $eventB = Event::factory()->create(['venue_id' => $venue->id]);

        $account = PaymentAccount::factory()->forVenue($venue)->shamcash('WALLET-A')->create();
        EventPaymentAccount::factory()->forEvent($eventA)->forPaymentAccount($account)->create();

        $order = Order::factory()->forEvent($eventB)->create(['venue_id' => $venue->id]);

        $this->expectException(PaymentAccountNotFoundException::class);

        app(PaymentAccountResolver::class)->resolveForOrder($order);
    }
}
