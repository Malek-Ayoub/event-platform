<?php

namespace App\Services\Commerce;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Models\Coupon;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\Commerce\Data\CreateCouponData;
use App\Services\Commerce\Data\UpdateCouponData;
use App\Services\OutboxService;
use App\Services\TransactionRunner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function __construct(
        private TransactionRunner $transactionRunner,
        private ActivityLogService $activityLogService,
        private OutboxService $outboxService,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Coupon::query()
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function createCoupon(CreateCouponData $data): Coupon
    {
        return $this->transactionRunner->run(function () use ($data): Coupon {
            $code = $this->normalizeCode($data->code);
            $this->assertUniqueCode($code);

            $coupon = Coupon::query()->create([
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

            $this->recordAudit($data->actor, $coupon, 'created', null, $this->snapshot($coupon), array_keys($this->snapshot($coupon)), $data->ipAddress);

            $this->outboxService->record(
                eventType: 'coupon.created',
                aggregate: $coupon,
                payload: ['code' => $coupon->code, 'discount_type' => $coupon->discount_type->value],
            );

            return $coupon->fresh();
        });
    }

    public function updateCoupon(Coupon $coupon, UpdateCouponData $data): Coupon
    {
        return $this->transactionRunner->run(function () use ($coupon, $data): Coupon {
            $locked = Coupon::query()->whereKey($coupon->id)->lockForUpdate()->firstOrFail();
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
                eventType: 'coupon.updated',
                aggregate: $locked,
                payload: ['changed_fields' => $changedFields],
            );

            return $locked;
        });
    }

    public function deleteCoupon(Coupon $coupon, ?User $actor = null, ?string $ipAddress = null): void
    {
        $this->transactionRunner->run(function () use ($coupon, $actor, $ipAddress): void {
            $locked = Coupon::query()->whereKey($coupon->id)->lockForUpdate()->firstOrFail();
            $oldValues = $this->snapshot($locked);

            $locked->delete();

            $this->recordAudit($actor, $locked, 'deleted', $oldValues, null, ['deleted_at'], $ipAddress);

            $this->outboxService->record(
                eventType: 'coupon.deleted',
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

        $exists = Coupon::query()
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
    private function snapshot(Coupon $coupon): array
    {
        return [
            'code' => $coupon->code,
            'discount_type' => $coupon->discount_type->value,
            'discount_value' => $coupon->discount_value,
            'max_uses' => $coupon->max_uses,
            'used_count' => $coupon->used_count,
            'is_active' => $coupon->is_active,
        ];
    }

    /**
     * @param  list<string>|null  $changedFields
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordAudit(
        ?User $actor,
        Coupon $entity,
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
