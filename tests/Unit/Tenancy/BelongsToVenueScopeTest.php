<?php

namespace Tests\Unit\Tenancy;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Domain\Tenancy\TenantContext;
use App\Exceptions\TenantNotResolvedException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Stubs\TenantScopedRecord;
use Tests\TestCase;

class BelongsToVenueScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tenant_scoped_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('venue_id');
            $table->string('name');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('tenant_scoped_records');

        parent::tearDown();
    }

    #[Test]
    public function it_applies_global_scope_for_current_tenant(): void
    {
        $context = new TenantContext;
        $context->bind(venueId: 1, source: 'subdomain');
        $this->app->instance(TenantContextInterface::class, $context);

        TenantScopedRecord::query()->insert([
            ['venue_id' => 1, 'name' => 'allowed', 'created_at' => now(), 'updated_at' => now()],
            ['venue_id' => 2, 'name' => 'blocked', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $records = TenantScopedRecord::query()->pluck('name')->all();

        $this->assertSame(['allowed'], $records);
    }

    #[Test]
    public function it_auto_fills_venue_id_on_create(): void
    {
        $context = new TenantContext;
        $context->bind(venueId: 3, source: 'subdomain');
        $this->app->instance(TenantContextInterface::class, $context);

        $record = TenantScopedRecord::query()->create(['name' => 'created']);

        $this->assertSame(3, $record->venue_id);
    }

    #[Test]
    public function it_throws_when_querying_without_resolved_tenant(): void
    {
        $this->app->instance(TenantContextInterface::class, new TenantContext);

        $this->expectException(TenantNotResolvedException::class);

        TenantScopedRecord::query()->get();
    }
}
