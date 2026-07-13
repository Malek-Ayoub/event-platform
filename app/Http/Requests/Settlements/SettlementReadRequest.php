<?php

namespace App\Http\Requests\Settlements;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Http\Requests\Api\PaginatedListRequest;
use App\Services\Authorization\PermissionService;
use App\Services\Settlements\Data\SettlementDateRange;
use Illuminate\Support\Carbon;

abstract class SettlementReadRequest extends PaginatedListRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);
    }

    public function dateRange(): SettlementDateRange
    {
        $from = $this->validated('from');
        $to = $this->validated('to');

        return new SettlementDateRange(
            from: $from !== null ? Carbon::parse((string) $from)->startOfDay() : null,
            to: $to !== null ? Carbon::parse((string) $to)->endOfDay() : null,
        );
    }

    protected function canViewOrganizerSettlement(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenantContext = app(TenantContextInterface::class);

        if (! $tenantContext->isResolved()) {
            return false;
        }

        return app(PermissionService::class)->can(
            $user,
            'reports.view',
            $tenantContext->requireVenueId(),
        );
    }
}
