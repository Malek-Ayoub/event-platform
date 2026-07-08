<?php

namespace Tests\Unit\Services\PlatformSettings;

use App\Exceptions\PlatformSettings\PlatformSettingSingletonViolationException;
use App\Exceptions\StaleModelException;
use App\Models\ActivityLog;
use App\Models\OutboxEvent;
use App\Models\PlatformSetting;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\OutboxService;
use App\Services\PlatformSettings\Data\UpdatePlatformSettingData;
use App\Services\PlatformSettings\PlatformSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class PlatformSettingServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_singleton_platform_setting(): void
    {
        $setting = PlatformSetting::factory()->create();

        $result = app(PlatformSettingService::class)->get();

        $this->assertSame($setting->id, $result->id);
    }

    #[Test]
    public function it_updates_platform_setting_with_optimistic_lock_activity_log_and_outbox(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $setting = PlatformSetting::factory()->create([
            'commission_rate' => 1.00,
            'settings' => ['default_currency' => 'USD'],
            'version' => 1,
        ]);

        $updated = app(PlatformSettingService::class)->update(new UpdatePlatformSettingData(
            expectedVersion: 1,
            commissionRate: '2.50',
            settings: ['support_email' => 'support@example.com'],
            actor: $admin,
            ipAddress: '127.0.0.1',
        ));

        $this->assertSame('2.50', $updated->commission_rate);
        $this->assertSame(2, $updated->version);
        $this->assertSame('USD', $updated->settings['default_currency']);
        $this->assertSame('support@example.com', $updated->settings['support_email']);

        $this->assertDatabaseHas('activity_logs', [
            'entity_type' => PlatformSetting::class,
            'entity_id' => $setting->id,
            'action' => 'updated',
            'actor_user_id' => $admin->id,
        ]);

        $outbox = OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('event_type', 'platform_setting.updated')->first();
        $this->assertNotNull($outbox);
        $this->assertArrayHasKey('occurred_at', $outbox->payload);
    }

    #[Test]
    public function it_throws_stale_model_exception_on_version_conflict(): void
    {
        PlatformSetting::factory()->create(['version' => 2]);

        $this->expectException(StaleModelException::class);

        app(PlatformSettingService::class)->update(new UpdatePlatformSettingData(
            expectedVersion: 1,
            commissionRate: '3.00',
        ));
    }

    #[Test]
    public function it_rejects_multiple_platform_setting_rows(): void
    {
        PlatformSetting::factory()->count(2)->create();

        $this->expectException(PlatformSettingSingletonViolationException::class);

        app(PlatformSettingService::class)->get();
    }

    #[Test]
    public function it_rolls_back_when_activity_log_fails(): void
    {
        PlatformSetting::factory()->create([
            'commission_rate' => 1.00,
            'version' => 1,
        ]);

        $this->mock(ActivityLogService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('log failed'));
        });

        try {
            app(PlatformSettingService::class)->update(new UpdatePlatformSettingData(
                expectedVersion: 1,
                commissionRate: '4.00',
            ));
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame('1.00', PlatformSetting::query()->first()->commission_rate);
        $this->assertSame(1, PlatformSetting::query()->first()->version);
    }

    #[Test]
    public function it_rolls_back_when_outbox_fails(): void
    {
        PlatformSetting::factory()->create([
            'commission_rate' => 1.00,
            'version' => 1,
        ]);

        $this->mock(OutboxService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('outbox failed'));
        });

        try {
            app(PlatformSettingService::class)->update(new UpdatePlatformSettingData(
                expectedVersion: 1,
                commissionRate: '4.00',
            ));
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame('1.00', PlatformSetting::query()->first()->commission_rate);
        $this->assertSame(0, ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->count());
    }
}
