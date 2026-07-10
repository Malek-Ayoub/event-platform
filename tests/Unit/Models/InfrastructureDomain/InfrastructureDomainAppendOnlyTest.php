<?php

namespace Tests\Unit\Models\InfrastructureDomain;

use App\Models\ActivityLog;
use App\Support\Concerns\HasOptimisticLock;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InfrastructureDomainAppendOnlyTest extends TestCase
{
    /** @var list<class-string> */
    private array $appendOnlyModels = [
        ActivityLog::class,
    ];

    #[Test]
    public function append_only_models_disable_updated_at(): void
    {
        $this->assertNull(ActivityLog::UPDATED_AT);
    }

    #[Test]
    public function append_only_models_have_no_updated_at_column(): void
    {
        $this->assertFalse(Schema::hasColumn('activity_logs', 'updated_at'));
    }

    #[Test]
    public function append_only_models_do_not_use_soft_deletes_or_optimistic_lock(): void
    {
        foreach ($this->appendOnlyModels as $modelClass) {
            $this->assertNotContains(
                SoftDeletes::class,
                class_uses_recursive($modelClass),
                "{$modelClass} must not use SoftDeletes",
            );
            $this->assertNotContains(
                HasOptimisticLock::class,
                class_uses_recursive($modelClass),
                "{$modelClass} must not use HasOptimisticLock",
            );
        }
    }
}
