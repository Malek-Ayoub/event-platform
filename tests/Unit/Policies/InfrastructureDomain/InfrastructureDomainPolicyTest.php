<?php

namespace Tests\Unit\Policies\InfrastructureDomain;

use App\Models\ActivityLog;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\Notification;
use App\Models\PlatformSetting;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Models\Venue;
use App\Policies\ActivityLogPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\PlatformSettingPolicy;
use App\Policies\TemplatePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\SeedsPermissions;
use Tests\TestCase;

class InfrastructureDomainPolicyTest extends TestCase
{
    use RefreshDatabase, SeedsPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionsCatalog();
    }

    #[Test]
    public function super_admin_can_manage_all_infrastructure_policies(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $fixtures = $this->createInfrastructureFixtures();

        $this->assertTrue(app(PlatformSettingPolicy::class)->update($admin, $fixtures['platformSetting']));
        $this->assertTrue(app(NotificationPolicy::class)->view($admin, $fixtures['notification']));
        $this->assertTrue(app(TemplatePolicy::class)->update($admin, $fixtures['emailTemplate']));
        $this->assertTrue(app(TemplatePolicy::class)->update($admin, $fixtures['smsTemplate']));
        $this->assertTrue(app(ActivityLogPolicy::class)->view($admin, $fixtures['activityLog']));
    }

    #[Test]
    public function owner_can_manage_templates_and_view_activity_logs_in_own_venue(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);
        $fixtures = $this->createInfrastructureFixtures($venue, $owner);

        $this->assertTrue(app(TemplatePolicy::class)->create($owner));
        $this->assertTrue(app(TemplatePolicy::class)->update($owner, $fixtures['emailTemplate']));
        $this->assertTrue(app(TemplatePolicy::class)->update($owner, $fixtures['smsTemplate']));
        $this->assertTrue(app(ActivityLogPolicy::class)->view($owner, $fixtures['activityLog']));
        $this->assertTrue(app(NotificationPolicy::class)->view($owner, $fixtures['notification']));
        $this->assertTrue(app(NotificationPolicy::class)->update($owner, $fixtures['notification']));
    }

    #[Test]
    public function owner_cannot_manage_platform_settings(): void
    {
        ['user' => $owner] = $this->createVenueOwner();
        $fixtures = $this->createInfrastructureFixtures();

        $this->assertFalse(app(PlatformSettingPolicy::class)->view($owner, $fixtures['platformSetting']));
        $this->assertFalse(app(PlatformSettingPolicy::class)->update($owner, $fixtures['platformSetting']));
    }

    #[Test]
    public function staff_can_view_activity_logs_but_not_manage_templates(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);
        $this->bindTenant($venue->id);
        $fixtures = $this->createInfrastructureFixtures($venue);

        $this->assertTrue(app(ActivityLogPolicy::class)->view($staff, $fixtures['activityLog']));
        $this->assertFalse(app(TemplatePolicy::class)->create($staff));
        $this->assertFalse(app(TemplatePolicy::class)->update($staff, $fixtures['emailTemplate']));
        $this->assertFalse(app(ActivityLogPolicy::class)->create($staff));
        $this->assertFalse(app(ActivityLogPolicy::class)->update($staff, $fixtures['activityLog']));
    }

    #[Test]
    public function customer_can_only_view_and_update_own_notifications(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $customer = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->bindTenant($venue->id);

        $ownNotification = Notification::factory()->forVenue($venue)->forUser($customer)->create();
        $foreignNotification = Notification::factory()->forVenue($venue)->forUser($otherUser)->create();

        $this->assertTrue(app(NotificationPolicy::class)->view($customer, $ownNotification));
        $this->assertTrue(app(NotificationPolicy::class)->update($customer, $ownNotification));
        $this->assertFalse(app(NotificationPolicy::class)->view($customer, $foreignNotification));
        $this->assertFalse(app(NotificationPolicy::class)->update($customer, $foreignNotification));
    }

    #[Test]
    public function owner_cannot_access_infrastructure_resources_from_another_tenant(): void
    {
        ['user' => $ownerA] = $this->createVenueOwner();
        ['venue' => $venueB, 'user' => $ownerB] = $this->createVenueOwner();
        $this->bindTenant($venueB->id);
        $fixturesB = $this->createInfrastructureFixtures($venueB, $ownerB);

        $this->assertFalse(app(TemplatePolicy::class)->view($ownerA, $fixturesB['emailTemplate']));
        $this->assertFalse(app(TemplatePolicy::class)->update($ownerA, $fixturesB['emailTemplate']));
        $this->assertFalse(app(ActivityLogPolicy::class)->view($ownerA, $fixturesB['activityLog']));
        $this->assertFalse(app(NotificationPolicy::class)->view($ownerA, $fixturesB['notification']));
    }

    /**
     * @return array{
     *     platformSetting: PlatformSetting,
     *     notification: Notification,
     *     emailTemplate: EmailTemplate,
     *     smsTemplate: SmsTemplate,
     *     activityLog: ActivityLog
     * }
     */
    private function createInfrastructureFixtures(?Venue $venue = null, ?User $user = null): array
    {
        if ($venue === null) {
            ['venue' => $venue, 'user' => $user] = $this->createVenueOwner();
            $this->bindTenant($venue->id);
        }

        $user ??= User::factory()->create();
        $event = Event::factory()->create(['venue_id' => $venue->id]);

        return [
            'platformSetting' => PlatformSetting::factory()->create(),
            'notification' => Notification::factory()->forVenue($venue)->forUser($user)->create(),
            'emailTemplate' => EmailTemplate::factory()->forVenue($venue)->create(),
            'smsTemplate' => SmsTemplate::factory()->forVenue($venue)->create(),
            'activityLog' => ActivityLog::factory()->forVenue($venue)->forActor($user)->forEntity($event)->create(),
        ];
    }
}
