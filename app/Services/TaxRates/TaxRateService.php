<?php

namespace App\Services\TaxRates;

use App\Models\TaxRate;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\OutboxService;
use App\Services\TaxRates\Data\CreateTaxRateData;
use App\Services\TaxRates\Data\UpdateTaxRateData;
use App\Services\TransactionRunner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TaxRateService
{
    public function __construct(
        private TransactionRunner $transactionRunner,
        private ActivityLogService $activityLogService,
        private OutboxService $outboxService,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return TaxRate::query()
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function createTaxRate(CreateTaxRateData $data): TaxRate
    {
        return $this->transactionRunner->run(function () use ($data): TaxRate {
            $taxRate = TaxRate::query()->create([
                'name' => $data->name,
                'rate' => $data->rate,
                'is_active' => $data->isActive,
                'version' => 1,
            ]);

            $this->recordAudit($data->actor, $taxRate, 'created', null, $this->snapshot($taxRate), array_keys($this->snapshot($taxRate)), $data->ipAddress);

            $this->outboxService->record(
                eventType: 'tax_rate.created',
                aggregate: $taxRate,
                payload: ['name' => $taxRate->name, 'rate' => $taxRate->rate],
            );

            return $taxRate->fresh();
        });
    }

    public function updateTaxRate(TaxRate $taxRate, UpdateTaxRateData $data): TaxRate
    {
        return $this->transactionRunner->run(function () use ($taxRate, $data): TaxRate {
            $locked = TaxRate::query()->whereKey($taxRate->id)->lockForUpdate()->firstOrFail();
            $oldValues = $this->snapshot($locked);
            $attributes = [];
            $changedFields = [];

            if ($data->name !== null) {
                $attributes['name'] = $data->name;
                $changedFields[] = 'name';
            }

            if ($data->rate !== null) {
                $attributes['rate'] = $data->rate;
                $changedFields[] = 'rate';
            }

            if ($data->isActive !== null) {
                $attributes['is_active'] = $data->isActive;
                $changedFields[] = 'is_active';
            }

            if ($changedFields === []) {
                return $locked;
            }

            $locked->updateWithVersion($attributes, $data->expectedVersion);
            $locked = $locked->fresh();

            $this->recordAudit($data->actor, $locked, 'updated', $oldValues, $this->snapshot($locked), [...$changedFields, 'version'], $data->ipAddress);

            $this->outboxService->record(
                eventType: 'tax_rate.updated',
                aggregate: $locked,
                payload: ['changed_fields' => $changedFields, 'version' => $locked->version],
            );

            return $locked;
        });
    }

    public function deleteTaxRate(TaxRate $taxRate, ?User $actor = null, ?string $ipAddress = null): void
    {
        $this->transactionRunner->run(function () use ($taxRate, $actor, $ipAddress): void {
            $locked = TaxRate::query()->whereKey($taxRate->id)->lockForUpdate()->firstOrFail();
            $oldValues = $this->snapshot($locked);

            $locked->delete();

            $this->recordAudit($actor, $locked, 'deleted', $oldValues, null, ['deleted_at'], $ipAddress);

            $this->outboxService->record(
                eventType: 'tax_rate.deleted',
                aggregate: $locked,
                payload: ['name' => $locked->name],
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(TaxRate $taxRate): array
    {
        return [
            'name' => $taxRate->name,
            'rate' => $taxRate->rate,
            'is_active' => $taxRate->is_active,
            'version' => $taxRate->version,
        ];
    }

    /**
     * @param  list<string>|null  $changedFields
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordAudit(
        ?User $actor,
        TaxRate $entity,
        string $action,
        ?array $oldValues,
        ?array $newValues,
        ?array $changedFields,
        ?string $ipAddress,
    ): void {
        $this->activityLogService->record(
            actor: $actor,
            entity: $entity,
            action: $action,
            oldValues: $oldValues,
            newValues: $newValues,
            changedFields: $changedFields,
            ipAddress: $ipAddress,
        );
    }
}
