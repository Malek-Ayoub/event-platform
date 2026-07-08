<?php

namespace App\Services\Commerce;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Models\Event;
use App\Models\Product;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\Commerce\Data\CreateProductData;
use App\Services\Commerce\Data\UpdateProductData;
use App\Services\OutboxService;
use App\Services\TransactionRunner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(
        private TransactionRunner $transactionRunner,
        private ActivityLogService $activityLogService,
        private OutboxService $outboxService,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Product::query()
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function createProduct(CreateProductData $data): Product
    {
        return $this->transactionRunner->run(function () use ($data): Product {
            $venueId = (int) app(TenantContextInterface::class)->requireVenueId();
            $eventId = $this->resolveEventId($data->eventId, $venueId);

            $product = Product::query()->create([
                'event_id' => $eventId,
                'name' => $data->name,
                'description' => $data->description,
                'price' => $data->price,
                'is_active' => $data->isActive,
            ]);

            $this->recordAudit($data->actor, $product, 'created', null, $this->snapshot($product), array_keys($this->snapshot($product)), $data->ipAddress);

            $this->outboxService->record(
                eventType: 'product.created',
                aggregate: $product,
                payload: [
                    'name' => $product->name,
                    'price' => $product->price,
                    'event_id' => $product->event_id,
                    'is_active' => $product->is_active,
                ],
            );

            return $product->fresh();
        });
    }

    public function updateProduct(Product $product, UpdateProductData $data): Product
    {
        return $this->transactionRunner->run(function () use ($product, $data): Product {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $oldValues = $this->snapshot($locked);
            $attributes = [];
            $changedFields = [];

            if ($data->name !== null) {
                $attributes['name'] = $data->name;
                $changedFields[] = 'name';
            }

            if ($data->description !== null) {
                $attributes['description'] = $data->description;
                $changedFields[] = 'description';
            }

            if ($data->price !== null) {
                $attributes['price'] = $data->price;
                $changedFields[] = 'price';
            }

            if ($data->updateEventId) {
                $attributes['event_id'] = $this->resolveEventId($data->eventId, $locked->venue_id);
                $changedFields[] = 'event_id';
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
                eventType: 'product.updated',
                aggregate: $locked,
                payload: ['changed_fields' => $changedFields],
            );

            return $locked;
        });
    }

    public function deleteProduct(Product $product, ?User $actor = null, ?string $ipAddress = null): void
    {
        $this->transactionRunner->run(function () use ($product, $actor, $ipAddress): void {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $oldValues = $this->snapshot($locked);

            $locked->delete();

            $this->recordAudit($actor, $locked, 'deleted', $oldValues, null, ['deleted_at'], $ipAddress);

            $this->outboxService->record(
                eventType: 'product.deleted',
                aggregate: $locked,
                payload: ['name' => $locked->name],
            );
        });
    }

    private function resolveEventId(?int $eventId, int $venueId): ?int
    {
        if ($eventId === null) {
            return null;
        }

        $event = Event::query()->findOrFail($eventId);

        if ((int) $event->venue_id !== $venueId) {
            throw ValidationException::withMessages([
                'event_id' => ['The selected event is invalid.'],
            ]);
        }

        return $event->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Product $product): array
    {
        return [
            'name' => $product->name,
            'price' => $product->price,
            'event_id' => $product->event_id,
            'is_active' => $product->is_active,
        ];
    }

    /**
     * @param  list<string>|null  $changedFields
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordAudit(
        ?User $actor,
        Product $entity,
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
