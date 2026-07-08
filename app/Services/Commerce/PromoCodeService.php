<?php

namespace App\Services\Commerce;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\Commerce\Data\CreatePromoCodeData;
use App\Services\Commerce\Data\UpdatePromoCodeData;
use App\Services\OutboxService;
use App\Services\TransactionRunner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PromoCodeService
{
    public function __construct(
        private TransactionRunner $transactionRunner,
        private ActivityLogService $activityLogService,
        private OutboxService $outboxService,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return PromoCode::query()
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function createPromoCode(CreatePromoCodeData $data): PromoCode
    {
        return $this->transactionRunner->run(function () use ($data): PromoCode {
            $code = $this->normalizeCode($data->code);
            $this->assertUniqueCode($code);

            $promoCode = PromoCode::query()->create([
                'code' => $code,
                'discount_type' => $data->discountType,
                'discount_value' => $data->discountValue,
                'min_order_amount' => $data->minOrderAmount,
                'max_uses' => $data->maxUses,
                'used_count' => 0,
                'starts_at' => $data->startsAt,
                'expires_at' => $data->expiresAt,
                'is_active' => $data->isActive,
            ]);

            $this->recordAudit($data->actor, $promoCode, 'created', null, $this->snapshot($promoCode), array_keys($this->snapshot($promoCode)), $data->ipAddress);

            $this->outboxService->record(
                eventType: 'promo_code.created',
                aggregate: $promoCode,
                payload: ['code' => $promoCode->code, 'discount_type' => $promoCode->discount_type->value],
            );

            return $promoCode->fresh();
        });
    }

    public function updatePromoCode(PromoCode $promoCode, UpdatePromoCodeData $data): PromoCode
    {
        return $this->transactionRunner->run(function () use ($promoCode, $data): PromoCode {
            $locked = PromoCode::query()->whereKey($promoCode->id)->lockForUpdate()->firstOrFail();
            $oldValues = $this->snapshot($locked);
            $attributes = [];
            $changedFields = [];

            if ($data->code !== null) {
                $code = $this->normalizeCode($data->code);
                $this->assertUniqueCode($code, $locked->id);
                $attributes['code'] = $code;
                $changedFields[] = 'code';
            }

            if ($data->discountType !== null) {
                $attributes['discount_type'] = $data->discountType;
                $changedFields[] = 'discount_type';
            }

            if ($data->discountValue !== null) {
                $attributes['discount_value'] = $data->discountValue;
                $changedFields[] = 'discount_value';
            }

            if ($data->minOrderAmount !== null) {
                $attributes['min_order_amount'] = $data->minOrderAmount;
                $changedFields[] = 'min_order_amount';
            }

            if ($data->maxUses !== null) {
                $attributes['max_uses'] = $data->maxUses;
                $changedFields[] = 'max_uses';
            }

            if ($data->startsAt !== null) {
                $attributes['starts_at'] = $data->startsAt;
                $changedFields[] = 'starts_at';
            }

            if ($data->expiresAt !== null) {
                $attributes['expires_at'] = $data->expiresAt;
                $changedFields[] = 'expires_at';
            }

            if ($data->isActive !== null) {
                $attributes['is_active'] = $data->isActive;
                $changedFields[] = 'is_active';
            }

            if ($changedFields === []) {
                return $locked;
            }

            $locked->update($attributes);
            $locked = $locked->fresh();

            $this->recordAudit($data->actor, $locked, 'updated', $oldValues, $this->snapshot($locked), $changedFields, $data->ipAddress);

            $this->outboxService->record(
                eventType: 'promo_code.updated',
                aggregate: $locked,
                payload: ['changed_fields' => $changedFields],
            );

            return $locked;
        });
    }

    public function deletePromoCode(PromoCode $promoCode, ?User $actor = null, ?string $ipAddress = null): void
    {
        $this->transactionRunner->run(function () use ($promoCode, $actor, $ipAddress): void {
            $locked = PromoCode::query()->whereKey($promoCode->id)->lockForUpdate()->firstOrFail();
            $oldValues = $this->snapshot($locked);

            $locked->delete();

            $this->recordAudit($actor, $locked, 'deleted', $oldValues, null, ['deleted_at'], $ipAddress);

            $this->outboxService->record(
                eventType: 'promo_code.deleted',
                aggregate: $locked,
                payload: ['code' => $locked->code],
            );
        });
    }

    private function normalizeCode(string $code): string
    {
        return Str::upper(trim($code));
    }

    private function assertUniqueCode(string $code, ?int $ignoreId = null): void
    {
        $venueId = (int) app(TenantContextInterface::class)->requireVenueId();

        $exists = PromoCode::query()
            ->where('venue_id', $venueId)
            ->where('code', $code)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => ['The code has already been taken.'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(PromoCode $promoCode): array
    {
        return [
            'code' => $promoCode->code,
            'discount_type' => $promoCode->discount_type->value,
            'discount_value' => $promoCode->discount_value,
            'max_uses' => $promoCode->max_uses,
            'used_count' => $promoCode->used_count,
            'is_active' => $promoCode->is_active,
        ];
    }

    /**
     * @param  list<string>|null  $changedFields
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordAudit(
        ?User $actor,
        PromoCode $entity,
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
