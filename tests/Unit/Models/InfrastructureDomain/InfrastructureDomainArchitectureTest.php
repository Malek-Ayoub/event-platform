<?php

namespace Tests\Unit\Models\InfrastructureDomain;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\EmailTemplate;
use App\Models\Media;
use App\Models\Notification;
use App\Models\OutboxEvent;
use App\Models\PlatformSetting;
use App\Models\SmsTemplate;
use App\Models\WebhookLog;
use App\Support\Concerns\BelongsToVenue;
use App\Support\Concerns\HasOptimisticLock;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class InfrastructureDomainArchitectureTest extends TestCase
{
    /** @var list<class-string> */
    private array $tenantScopedModels = [
        Notification::class,
        EmailTemplate::class,
        SmsTemplate::class,
        ActivityLog::class,
        OutboxEvent::class,
        Media::class,
        Document::class,
    ];

    /** @var list<class-string> */
    private array $platformModels = [
        PlatformSetting::class,
        WebhookLog::class,
    ];

    /** @var list<class-string> */
    private array $allInfrastructureModels = [
        PlatformSetting::class,
        Notification::class,
        EmailTemplate::class,
        SmsTemplate::class,
        ActivityLog::class,
        WebhookLog::class,
        OutboxEvent::class,
        Media::class,
        Document::class,
    ];

    #[Test]
    public function tenant_scoped_infrastructure_models_use_belongs_to_venue(): void
    {
        foreach ($this->tenantScopedModels as $modelClass) {
            $this->assertContains(
                BelongsToVenue::class,
                class_uses_recursive($modelClass),
                "{$modelClass} should use BelongsToVenue",
            );
        }
    }

    #[Test]
    public function platform_models_do_not_use_belongs_to_venue(): void
    {
        foreach ($this->platformModels as $modelClass) {
            $this->assertNotContains(
                BelongsToVenue::class,
                class_uses_recursive($modelClass),
                "{$modelClass} must not use BelongsToVenue",
            );
        }
    }

    #[Test]
    public function only_platform_setting_uses_optimistic_lock(): void
    {
        $this->assertContains(HasOptimisticLock::class, class_uses_recursive(PlatformSetting::class));

        foreach (array_diff($this->allInfrastructureModels, [PlatformSetting::class]) as $modelClass) {
            $this->assertNotContains(
                HasOptimisticLock::class,
                class_uses_recursive($modelClass),
                "{$modelClass} must not use HasOptimisticLock",
            );
        }
    }

    #[Test]
    public function infrastructure_models_do_not_use_soft_deletes(): void
    {
        foreach ($this->allInfrastructureModels as $modelClass) {
            $this->assertNotContains(
                SoftDeletes::class,
                class_uses_recursive($modelClass),
                "{$modelClass} must not use SoftDeletes",
            );
        }
    }

    #[Test]
    public function append_only_logs_are_created_at_only(): void
    {
        $this->assertNull(ActivityLog::UPDATED_AT);
        $this->assertNull(WebhookLog::UPDATED_AT);
        $this->assertFalse(Schema::hasColumn('activity_logs', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('webhook_logs', 'updated_at'));
    }

    #[Test]
    public function notification_uses_uuid_primary_key(): void
    {
        $this->assertContains(HasUuids::class, class_uses_recursive(Notification::class));
        $this->assertSame('string', (new Notification)->getKeyType());
    }

    #[Test]
    public function infrastructure_models_do_not_define_appends(): void
    {
        foreach ($this->allInfrastructureModels as $modelClass) {
            $this->assertSame([], (new $modelClass)->getAppends(), "{$modelClass} must not define \$appends");
        }
    }

    #[Test]
    public function infrastructure_models_do_not_define_accessors_or_attribute_mutators(): void
    {
        foreach ($this->allInfrastructureModels as $modelClass) {
            $reflection = new ReflectionClass($modelClass);
            foreach ($reflection->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $modelClass) {
                    continue;
                }

                $name = $method->getName();
                if (str_starts_with($name, 'get') && str_ends_with($name, 'Attribute')) {
                    if (in_array($name, ['getUseFactoryAttribute'], true)) {
                        continue;
                    }

                    $this->fail("{$modelClass} defines accessor {$name} — models must stay anemic in Phase 4");
                }

                $returnType = $method->getReturnType();
                if ($returnType instanceof \ReflectionNamedType && $returnType->getName() === 'Illuminate\Database\Eloquent\Casts\Attribute') {
                    $this->fail("{$modelClass} defines Attribute mutator {$name} — not allowed in Phase 4");
                }
            }

            $this->assertTrue(true);
        }
    }
}
