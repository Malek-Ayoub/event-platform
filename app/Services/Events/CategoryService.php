<?php

namespace App\Services\Events;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Models\Category;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\Events\Data\CreateCategoryData;
use App\Services\Events\Data\UpdateCategoryData;
use App\Services\OutboxService;
use App\Services\TransactionRunner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class CategoryService
{
    public function __construct(
        private TransactionRunner $transactionRunner,
        private ActivityLogService $activityLogService,
        private OutboxService $outboxService,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Category::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function createCategory(CreateCategoryData $data): Category
    {
        return $this->transactionRunner->run(function () use ($data): Category {
            $venueId = (int) app(TenantContextInterface::class)->requireVenueId();

            $category = Category::query()->create([
                'name' => $data->name,
                'slug' => $this->resolveUniqueCategorySlug($data->slug ?? $data->name, $venueId),
                'description' => $data->description,
                'sort_order' => $data->sortOrder,
                'is_active' => $data->isActive,
            ]);

            $this->recordAudit($data->actor, $category, 'created', null, $this->snapshot($category), array_keys($this->snapshot($category)), $data->ipAddress);

            $this->outboxService->record(
                eventType: 'category.created',
                aggregate: $category,
                payload: ['name' => $category->name, 'slug' => $category->slug],
            );

            return $category->fresh();
        });
    }

    public function updateCategory(Category $category, UpdateCategoryData $data): Category
    {
        return $this->transactionRunner->run(function () use ($category, $data): Category {
            $locked = Category::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
            $oldValues = $this->snapshot($locked);
            $attributes = [];
            $changedFields = [];

            if ($data->name !== null) {
                $attributes['name'] = $data->name;
                $changedFields[] = 'name';
            }

            if ($data->slug !== null) {
                $attributes['slug'] = $this->resolveUniqueCategorySlug($data->slug, $locked->venue_id, $locked->id);
                $changedFields[] = 'slug';
            }

            if ($data->description !== null) {
                $attributes['description'] = $data->description;
                $changedFields[] = 'description';
            }

            if ($data->sortOrder !== null) {
                $attributes['sort_order'] = $data->sortOrder;
                $changedFields[] = 'sort_order';
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
                eventType: 'category.updated',
                aggregate: $locked,
                payload: ['changed_fields' => $changedFields],
            );

            return $locked;
        });
    }

    public function deleteCategory(Category $category, ?User $actor = null, ?string $ipAddress = null): void
    {
        $this->transactionRunner->run(function () use ($category, $actor, $ipAddress): void {
            $locked = Category::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
            $oldValues = $this->snapshot($locked);

            $locked->delete();

            $this->recordAudit($actor, $locked, 'deleted', $oldValues, null, ['deleted_at'], $ipAddress);

            $this->outboxService->record(
                eventType: 'category.deleted',
                aggregate: $locked,
                payload: ['slug' => $locked->slug],
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Category $category): array
    {
        return [
            'name' => $category->name,
            'slug' => $category->slug,
            'sort_order' => $category->sort_order,
            'is_active' => $category->is_active,
        ];
    }

    private function resolveUniqueCategorySlug(string $value, int $venueId, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'category';
        $candidate = $base;
        $suffix = 1;

        while (Category::query()
            ->where('venue_id', $venueId)
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @param  list<string>|null  $changedFields
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordAudit(
        ?User $actor,
        Category $entity,
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
