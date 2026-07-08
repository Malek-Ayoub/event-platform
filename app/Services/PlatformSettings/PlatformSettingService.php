<?php

namespace App\Services\PlatformSettings;

use App\Exceptions\PlatformSettings\PlatformSettingSingletonViolationException;
use App\Models\PlatformSetting;
use App\Services\ActivityLogService;
use App\Services\OutboxService;
use App\Services\PlatformSettings\Data\UpdatePlatformSettingData;
use App\Services\TransactionRunner;

class PlatformSettingService
{
    public function __construct(
        private TransactionRunner $transactionRunner,
        private ActivityLogService $activityLogService,
        private OutboxService $outboxService,
    ) {}

    public function get(): PlatformSetting
    {
        return $this->requireSingleton();
    }

    public function update(UpdatePlatformSettingData $data): PlatformSetting
    {
        return $this->transactionRunner->run(function () use ($data): PlatformSetting {
            $setting = PlatformSetting::query()
                ->orderBy('id')
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertSingleton();

            $oldValues = [
                'commission_rate' => $this->formatRate($setting->commission_rate),
                'settings' => $setting->settings,
                'version' => $setting->version,
            ];

            $attributes = [];
            $changedFields = [];

            if ($data->commissionRate !== null) {
                $attributes['commission_rate'] = $this->formatRate($data->commissionRate);
                $changedFields[] = 'commission_rate';
            }

            if ($data->settings !== null) {
                $attributes['settings'] = array_merge($setting->settings ?? [], $data->settings);
                $changedFields[] = 'settings';
            }

            if ($changedFields === []) {
                return $setting;
            }

            $setting->updateWithVersion($attributes, $data->expectedVersion);

            $newValues = [
                'commission_rate' => $this->formatRate($setting->commission_rate),
                'settings' => $setting->settings,
                'version' => $setting->version,
            ];

            $this->activityLogService->record(
                actor: $data->actor,
                entity: $setting,
                action: 'updated',
                oldValues: $oldValues,
                newValues: $newValues,
                changedFields: [...$changedFields, 'version'],
                ipAddress: $data->ipAddress,
            );

            $this->outboxService->record(
                eventType: 'platform_setting.updated',
                aggregate: $setting,
                payload: [
                    'commission_rate' => $setting->commission_rate,
                    'version' => $setting->version,
                    'changed_fields' => $changedFields,
                ],
            );

            return $setting->fresh();
        });
    }

    private function requireSingleton(): PlatformSetting
    {
        $this->assertSingleton();

        return PlatformSetting::query()->orderBy('id')->firstOrFail();
    }

    private function assertSingleton(): void
    {
        $count = PlatformSetting::query()->count();

        if ($count !== 1) {
            throw PlatformSettingSingletonViolationException::detected($count);
        }
    }

    private function formatRate(mixed $rate): string
    {
        return number_format((float) $rate, 2, '.', '');
    }
}
