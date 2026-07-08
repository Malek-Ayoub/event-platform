<?php

namespace App\Services\Commerce;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\Commerce\Data\CreateProductVariantData;
use App\Services\Commerce\Data\UpdateProductVariantData;
use App\Services\OutboxService;
use App\Services\TransactionRunner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductVariantService
{
    public function __construct(
        private TransactionRunner $transactionRunner,
        private ActivityLogService $activityLogService,
        private OutboxService $outboxService,
    ) {}

    public function listForProduct(Product $product, int $perPage = 15): LengthAwarePaginator
    {
        return ProductVariant::query()
            ->where('product_id', $product->id)
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function createProductVariant(CreateProductVariantData $data): ProductVariant
    {
        return $this->transactionRunner->run(function () use ($data): ProductVariant {
            $product = Product::query()->findOrFail($data->productId);

            $variant = ProductVariant::query()->create([
                'venue_id' => $product->venue_id,
                'product_id' => $product->id,
                'name' => $data->name,
                'sku' => $data->sku,
                'price_override' => $data->priceOverride,
                'is_active' => $data->isActive,
            ]);

            $this->recordAudit($data->actor, $variant, 'created', null, $this->snapshot($variant), array_keys($this->snapshot($variant)), $data->ipAddress);

            $this->outboxService->record(
                eventType: 'product_variant.created',
                aggregate: $variant,
                payload: [
                    'product_id' => $variant->product_id,
                    'name' => $variant->name,
                    'price_override' => $variant->price_override,
                ],
            );

            return $variant->fresh();
        });
    }

    public function updateProductVariant(ProductVariant $variant, UpdateProductVariantData $data): ProductVariant
    {
        return $this->transactionRunner->run(function () use ($variant, $data): ProductVariant {
            $locked = ProductVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $oldValues = $this->snapshot($locked);
            $attributes = [];
            $changedFields = [];

            if ($data->name !== null) {
                $attributes['name'] = $data->name;
                $changedFields[] = 'name';
            }

            if ($data->sku !== null) {
                $attributes['sku'] = $data->sku;
                $changedFields[] = 'sku';
            }

            if ($data->priceOverride !== null) {
                $attributes['price_override'] = $data->priceOverride;
                $changedFields[] = 'price_override';
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
                eventType: 'product_variant.updated',
                aggregate: $locked,
                payload: [
                    'product_id' => $locked->product_id,
                    'changed_fields' => $changedFields,
                ],
            );

            return $locked;
        });
    }

    public function deleteProductVariant(ProductVariant $variant, ?User $actor = null, ?string $ipAddress = null): void
    {
        $this->transactionRunner->run(function () use ($variant, $actor, $ipAddress): void {
            $locked = ProductVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $oldValues = $this->snapshot($locked);

            $locked->delete();

            $this->recordAudit($actor, $locked, 'deleted', $oldValues, null, ['deleted_at'], $ipAddress);

            $this->outboxService->record(
                eventType: 'product_variant.deleted',
                aggregate: $locked,
                payload: ['product_id' => $locked->product_id, 'name' => $locked->name],
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ProductVariant $variant): array
    {
        return [
            'product_id' => $variant->product_id,
            'name' => $variant->name,
            'sku' => $variant->sku,
            'price_override' => $variant->price_override,
            'is_active' => $variant->is_active,
        ];
    }

    /**
     * @param  list<string>|null  $changedFields
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordAudit(
        ?User $actor,
        ProductVariant $entity,
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
