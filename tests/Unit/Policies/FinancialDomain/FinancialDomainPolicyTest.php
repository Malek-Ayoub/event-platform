<?php

namespace Tests\Unit\Policies\FinancialDomain;

use App\Models\Event;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\User;
use App\Models\Venue;
use App\Policies\PaymentTransactionPolicy;
use App\Policies\RefundPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\SeedsPermissions;
use Tests\TestCase;

class FinancialDomainPolicyTest extends TestCase
{
    use RefreshDatabase, SeedsPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionsCatalog();
    }

    #[Test]
    public function super_admin_can_manage_payment_transactions_and_refunds(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $fixtures = $this->createFinancialFixtures();

        $this->assertTrue(app(PaymentTransactionPolicy::class)->update($admin, $fixtures['paymentTransaction']));
        $this->assertTrue(app(RefundPolicy::class)->update($admin, $fixtures['refund']));
    }

    #[Test]
    public function owner_can_manage_payment_transactions_and_refunds_in_own_venue(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);
        $fixtures = $this->createFinancialFixtures($venue);

        $this->assertTrue(app(PaymentTransactionPolicy::class)->create($owner));
        $this->assertTrue(app(PaymentTransactionPolicy::class)->update($owner, $fixtures['paymentTransaction']));
        $this->assertTrue(app(RefundPolicy::class)->create($owner));
        $this->assertTrue(app(RefundPolicy::class)->update($owner, $fixtures['refund']));
    }

    #[Test]
    public function staff_can_manage_payment_transactions_but_not_process_refunds(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);
        $this->bindTenant($venue->id);
        $fixtures = $this->createFinancialFixtures($venue);

        $this->assertTrue(app(PaymentTransactionPolicy::class)->view($staff, $fixtures['paymentTransaction']));
        $this->assertTrue(app(PaymentTransactionPolicy::class)->update($staff, $fixtures['paymentTransaction']));
        $this->assertTrue(app(RefundPolicy::class)->view($staff, $fixtures['refund']));
        $this->assertFalse(app(RefundPolicy::class)->create($staff));
        $this->assertFalse(app(RefundPolicy::class)->update($staff, $fixtures['refund']));
    }

    #[Test]
    public function customer_cannot_access_financial_resources(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $customer = User::factory()->create();
        $this->bindTenant($venue->id);
        $fixtures = $this->createFinancialFixtures($venue);

        $this->assertFalse(app(PaymentTransactionPolicy::class)->view($customer, $fixtures['paymentTransaction']));
        $this->assertFalse(app(PaymentTransactionPolicy::class)->create($customer));
        $this->assertFalse(app(RefundPolicy::class)->view($customer, $fixtures['refund']));
        $this->assertFalse(app(RefundPolicy::class)->create($customer));
    }

    #[Test]
    public function owner_cannot_manage_financial_resources_from_another_tenant(): void
    {
        ['user' => $ownerA] = $this->createVenueOwner();
        ['venue' => $venueB] = $this->createVenueOwner();
        $this->bindTenant($venueB->id);
        $fixturesB = $this->createFinancialFixtures($venueB);

        $this->assertFalse(app(PaymentTransactionPolicy::class)->view($ownerA, $fixturesB['paymentTransaction']));
        $this->assertFalse(app(PaymentTransactionPolicy::class)->update($ownerA, $fixturesB['paymentTransaction']));
        $this->assertFalse(app(RefundPolicy::class)->view($ownerA, $fixturesB['refund']));
        $this->assertFalse(app(RefundPolicy::class)->update($ownerA, $fixturesB['refund']));
    }

    /**
     * @return array{paymentTransaction: PaymentTransaction, refund: Refund}
     */
    private function createFinancialFixtures(?Venue $venue = null): array
    {
        if ($venue === null) {
            ['venue' => $venue] = $this->createVenueOwner();
            $this->bindTenant($venue->id);
        }

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $paymentTransaction = PaymentTransaction::factory()->forOrder($order)->create();
        $refund = Refund::factory()->forPaymentTransaction($paymentTransaction)->create();

        return [
            'paymentTransaction' => $paymentTransaction,
            'refund' => $refund,
        ];
    }
}
