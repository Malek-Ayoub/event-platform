<?php

namespace Tests\Feature\Events;

use App\Enums\EventDomain\EventStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tenancy.base_domain', 'localhost');
    }

    /**
     * @return array{owner: User, venue: Venue, token: string}
     */
    private function authenticateVenueOwner(): array
    {
        $this->seedPermissionsCatalog();
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $token = $owner->createToken('api')->plainTextToken;

        $this->withTenantHost($venue->subdomain);

        return ['owner' => $owner, 'venue' => $venue, 'token' => $token];
    }

    #[Test]
    public function owner_can_create_and_list_events(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();

        $create = $this->withToken($token)->postJson('/api/tenant/events', [
            'name' => 'Launch Party',
            'description' => 'Opening night',
            'start_datetime' => now()->addDays(3)->toIso8601String(),
            'end_datetime' => now()->addDays(4)->toIso8601String(),
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('data.name', 'Launch Party')
            ->assertJsonPath('data.status', EventStatus::Draft->value)
            ->assertJsonPath('data.slug', 'launch-party');

        $this->withToken($token)->getJson('/api/tenant/events')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);

        $this->assertDatabaseHas('events', [
            'venue_id' => $venue->id,
            'name' => 'Launch Party',
        ]);
    }

    #[Test]
    public function owner_can_publish_and_archive_event(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();

        $create = $this->withToken($token)->postJson('/api/tenant/events', [
            'name' => 'Publish Me',
            'start_datetime' => now()->addDays(3)->toIso8601String(),
            'end_datetime' => now()->addDays(4)->toIso8601String(),
        ]);

        $create->assertCreated();
        $eventId = $create->json('data.id');

        $this->withToken($token)->postJson("/api/tenant/events/{$eventId}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', EventStatus::Published->value);

        $this->withToken($token)->postJson("/api/tenant/events/{$eventId}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', EventStatus::Completed->value);
    }

    #[Test]
    public function owner_can_manage_categories_and_ticket_types(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();

        $categoryResponse = $this->withToken($token)->postJson('/api/tenant/categories', [
            'name' => 'Concerts',
            'sort_order' => 1,
        ]);

        $categoryResponse->assertCreated()->assertJsonPath('data.slug', 'concerts');
        $categoryId = $categoryResponse->json('data.id');

        $event = Event::factory()->create([
            'venue_id' => $venue->id,
            'category_id' => $categoryId,
        ]);

        $ticketTypeResponse = $this->withToken($token)->postJson("/api/tenant/events/{$event->id}/ticket-types", [
            'name' => 'General Admission',
            'price' => '49.99',
            'quantity' => 100,
        ]);

        $ticketTypeResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'General Admission')
            ->assertJsonPath('data.quantity_sold', 0);

        $ticketTypeId = $ticketTypeResponse->json('data.id');

        $this->withToken($token)->getJson("/api/tenant/events/{$event->id}/ticket-types")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)->getJson("/api/tenant/ticket-types/{$ticketTypeId}")
            ->assertOk()
            ->assertJsonPath('data.price', '49.99');

        $this->assertInstanceOf(Category::class, Category::query()->find($categoryId));
        $this->assertInstanceOf(TicketType::class, TicketType::query()->find($ticketTypeId));
    }

    #[Test]
    public function staff_without_permission_cannot_create_events(): void
    {
        $this->seedPermissionsCatalog();
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);
        $token = $staff->createToken('api')->plainTextToken;

        $this->withTenantHost($venue->subdomain)
            ->withToken($token)
            ->postJson('/api/tenant/events', [
                'name' => 'Blocked',
                'start_datetime' => now()->addDay()->toIso8601String(),
                'end_datetime' => now()->addDays(2)->toIso8601String(),
            ])
            ->assertForbidden();
    }
}
