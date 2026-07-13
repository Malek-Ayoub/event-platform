<?php

namespace Tests\Feature\Reports;

use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Models\Venue;
use App\Enums\OrdersDomain\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportsApiEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function organizer_report_returns_zero_values_for_empty_period(): void
    {
        $this->seedPermissionsCatalog();
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $token = $owner->createToken('api')->plainTextToken;

        $this->withTenantHost($venue->subdomain)
            ->withToken($token)
            ->getJson('/api/tenant/organizer/reports?from=2099-01-01&to=2099-01-31')
            ->assertOk()
            ->assertJsonPath('data.sales.gross_sales', '0.00')
            ->assertJsonPath('data.sales.orders_count', 0)
            ->assertJsonPath('data.sales.tickets_sold', 0)
            ->assertJsonPath('data.sales.average_order_value', '0.00')
            ->assertJsonPath('data.revenue.gross_revenue', '0.00')
            ->assertJsonPath('data.revenue.refunded_amount', '0.00')
            ->assertJsonPath('data.revenue.net_revenue', '0.00')
            ->assertJsonPath('data.attendance.tickets_issued', 0)
            ->assertJsonPath('data.attendance.checked_in', 0)
            ->assertJsonPath('data.attendance.attendance_rate', '0.00')
            ->assertJsonPath('data.commission.commission_due', '0.00')
            ->assertJsonPath('data.commission.commission_paid', '0.00')
            ->assertJsonPath('data.commission.outstanding_commission', '0.00')
            ->assertJsonPath('data.meta.currency', 'USD');
    }

    #[Test]
    public function admin_report_returns_zero_values_for_empty_period(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/reports?from=2099-01-01&to=2099-01-31')
            ->assertOk()
            ->assertJsonPath('data.platform.gross_revenue', '0.00')
            ->assertJsonPath('data.platform.net_revenue', '0.00')
            ->assertJsonPath('data.platform.orders_count', 0)
            ->assertJsonPath('data.platform.tickets_sold', 0)
            ->assertJsonPath('data.platform.active_venues', 0)
            ->assertJsonPath('data.commissions.commission_due', '0.00')
            ->assertJsonPath('data.commissions.commission_paid', '0.00')
            ->assertJsonPath('data.commissions.commission_adjustments', '0.00')
            ->assertJsonPath('data.commissions.outstanding_commission', '0.00')
            ->assertJsonPath('data.commissions.monthly', [])
            ->assertJsonPath('data.refunds.refunds_count', 0)
            ->assertJsonPath('data.refunds.refunded_amount', '0.00')
            ->assertJsonPath('data.refunds.refund_rate', '0.00')
            ->assertJsonPath('data.top_venues', [])
            ->assertJsonPath('data.top_events', [])
            ->assertJsonPath('data.payment_methods', []);
    }

    #[Test]
    public function organizer_report_rejects_invalid_date_range(): void
    {
        $this->seedPermissionsCatalog();
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $token = $owner->createToken('api')->plainTextToken;

        $this->withTenantHost($venue->subdomain)
            ->withToken($token)
            ->getJson('/api/tenant/organizer/reports?from=2026-02-01&to=2026-01-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    }

    #[Test]
    public function admin_report_rejects_invalid_date_range_and_excessive_limit(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/reports?from=2026-02-01&to=2026-01-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);

        $this->withToken($token)
            ->getJson('/api/admin/reports?limit=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limit']);
    }

    #[Test]
    public function admin_top_venues_use_stable_ordering_when_gross_sales_are_tied(): void
    {
        $lowerId = Venue::factory()->create(['name' => 'Lower Id Venue']);
        $higherId = Venue::factory()->create(['name' => 'Higher Id Venue']);

        if ($lowerId->id > $higherId->id) {
            [$lowerId, $higherId] = [$higherId, $lowerId];
        }

        foreach ([$lowerId, $higherId] as $venue) {
            $this->bindTenant($venue->id);
            $event = Event::factory()->create(['venue_id' => $venue->id]);
            Order::factory()->forEvent($event)->create([
                'total' => '100.00',
                'status' => OrderStatus::Paid,
                'updated_at' => now(),
            ]);
        }

        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/reports?limit=2')
            ->assertOk()
            ->assertJsonPath('data.top_venues.0.venue_id', $lowerId->id)
            ->assertJsonPath('data.top_venues.1.venue_id', $higherId->id);
    }
}
