<?php

namespace Tests\Unit\Tenancy;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Domain\Tenancy\TenantContext;
use App\Exceptions\CrossTenantAccessException;
use App\Policies\BasePolicy;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Tests\Stubs\TenantScopedRecord;
use Tests\TestCase;

class CrossTenantAccessTest extends TestCase
{
    #[Test]
    public function model_ensure_same_tenant_blocks_cross_tenant_access(): void
    {
        $context = new TenantContext;
        $context->bind(venueId: 1, source: 'subdomain');
        $this->app->instance(TenantContextInterface::class, $context);

        $record = new TenantScopedRecord(['venue_id' => 2, 'name' => 'other']);

        $this->expectException(CrossTenantAccessException::class);

        $record->ensureSameTenant();
    }

    #[Test]
    public function base_policy_blocks_cross_tenant_access(): void
    {
        $context = new TenantContext;
        $context->bind(venueId: 10, source: 'subdomain');
        $this->app->instance(TenantContextInterface::class, $context);

        $policy = new class($context) extends BasePolicy
        {
            public function check(Model $model): void
            {
                $this->authorizeSameTenant($model);
            }
        };

        $foreignModel = new class extends Model
        {
            protected $table = 'foreign_models';

            protected $guarded = [];
        };

        $foreignModel->setAttribute('venue_id', 11);

        $this->expectException(CrossTenantAccessException::class);

        $policy->check($foreignModel);
    }
}
