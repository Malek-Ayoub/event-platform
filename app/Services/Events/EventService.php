<?php

namespace App\Services\Events;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Enums\EventDomain\EventStatus;
use App\Exceptions\Events\InvalidEventStateTransitionException;
use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\Events\Data\CreateEventData;
use App\Services\Events\Data\UpdateEventData;
use App\Services\OutboxService;
use App\Services\TransactionRunner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class EventService
{
    public function __construct(
        private TransactionRunner $transactionRunner,
        private ActivityLogService $activityLogService,
        private OutboxService $outboxService,
        private EventStateMachine $stateMachine,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Event::query()
            ->with(['category'])
            ->orderByDesc('start_datetime')
            ->paginate($perPage);
    }

    public function createEvent(CreateEventData $data): Event
    {
        return $this->transactionRunner->run(function () use ($data): Event {
            if ($data->categoryId !== null) {
                Category::query()->findOrFail($data->categoryId);
            }

            $slug = $this->resolveUniqueEventSlug(
                $data->slug ?? $data->name,
                (int) app(TenantContextInterface::class)->requireVenueId(),
            );

            $event = Event::query()->create([
                'name' => $data->name,
                'slug' => $slug,
                'category_id' => $data->categoryId,
                'description' => $data->description,
                'banner_url' => $data->bannerUrl,
                'gallery' => $data->gallery,
                'video_url' => $data->videoUrl,
                'dj_info' => $data->djInfo,
                'start_datetime' => $data->startDatetime,
                'end_datetime' => $data->endDatetime,
                'status' => EventStatus::Draft,
                'version' => 1,
            ]);

            $this->recordAudit(
                actor: $data->actor,
                entity: $event,
                action: 'created',
                oldValues: null,
                newValues: $this->eventSnapshot($event),
                changedFields: array_keys($this->eventSnapshot($event)),
                ipAddress: $data->ipAddress,
            );

            $this->outboxService->record(
                eventType: 'event.created',
                aggregate: $event,
                payload: [
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'status' => $event->status->value,
                    'category_id' => $event->category_id,
                ],
            );

            return $event->fresh(['category']);
        });
    }

    public function updateEvent(Event $event, UpdateEventData $data): Event
    {
        return $this->transactionRunner->run(function () use ($event, $data): Event {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            if ($data->categoryId !== null) {
                Category::query()->findOrFail($data->categoryId);
            }

            $oldValues = $this->eventSnapshot($locked);
            $attributes = [];
            $changedFields = [];

            if ($data->name !== null) {
                $attributes['name'] = $data->name;
                $changedFields[] = 'name';
            }

            if ($data->slug !== null) {
                $attributes['slug'] = $this->resolveUniqueEventSlug($data->slug, $locked->venue_id, $locked->id);
                $changedFields[] = 'slug';
            }

            if ($data->categoryId !== null) {
                $attributes['category_id'] = $data->categoryId;
                $changedFields[] = 'category_id';
            }

            if ($data->description !== null) {
                $attributes['description'] = $data->description;
                $changedFields[] = 'description';
            }

            if ($data->bannerUrl !== null) {
                $attributes['banner_url'] = $data->bannerUrl;
                $changedFields[] = 'banner_url';
            }

            if ($data->gallery !== null) {
                $attributes['gallery'] = $data->gallery;
                $changedFields[] = 'gallery';
            }

            if ($data->videoUrl !== null) {
                $attributes['video_url'] = $data->videoUrl;
                $changedFields[] = 'video_url';
            }

            if ($data->djInfo !== null) {
                $attributes['dj_info'] = $data->djInfo;
                $changedFields[] = 'dj_info';
            }

            if ($data->startDatetime !== null) {
                $attributes['start_datetime'] = $data->startDatetime;
                $changedFields[] = 'start_datetime';
            }

            if ($data->endDatetime !== null) {
                $attributes['end_datetime'] = $data->endDatetime;
                $changedFields[] = 'end_datetime';
            }

            if ($changedFields === []) {
                return $locked->fresh(['category']);
            }

            $locked->updateWithVersion($attributes, $data->expectedVersion);
            $locked = $locked->fresh(['category']);

            $this->recordAudit(
                actor: $data->actor,
                entity: $locked,
                action: 'updated',
                oldValues: $oldValues,
                newValues: $this->eventSnapshot($locked),
                changedFields: [...$changedFields, 'version'],
                ipAddress: $data->ipAddress,
            );

            $this->outboxService->record(
                eventType: 'event.updated',
                aggregate: $locked,
                payload: [
                    'status' => $locked->status->value,
                    'version' => $locked->version,
                    'changed_fields' => $changedFields,
                ],
            );

            return $locked;
        });
    }

    public function publishEvent(Event $event, ?User $actor = null, ?string $ipAddress = null): Event
    {
        return $this->transitionEvent($event, EventStatus::Published, 'event.published', $actor, $ipAddress, idempotent: true);
    }

    public function archiveEvent(Event $event, ?User $actor = null, ?string $ipAddress = null): Event
    {
        if ($event->status === EventStatus::Completed) {
            return $event->fresh(['category']);
        }

        if (! $this->stateMachine->canArchiveFrom($event->status)) {
            throw InvalidEventStateTransitionException::archiveFrom($event->status);
        }

        return $this->transitionEvent($event, EventStatus::Completed, 'event.archived', $actor, $ipAddress, idempotent: false);
    }

    public function deleteEvent(Event $event, ?User $actor = null, ?string $ipAddress = null): void
    {
        $this->transactionRunner->run(function () use ($event, $actor, $ipAddress): void {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $oldValues = $this->eventSnapshot($locked);

            $locked->delete();

            $this->recordAudit(
                actor: $actor,
                entity: $locked,
                action: 'deleted',
                oldValues: $oldValues,
                newValues: null,
                changedFields: ['deleted_at'],
                ipAddress: $ipAddress,
            );

            $this->outboxService->record(
                eventType: 'event.deleted',
                aggregate: $locked,
                payload: [
                    'slug' => $locked->slug,
                    'status' => $locked->status->value,
                ],
            );
        });
    }

    private function transitionEvent(
        Event $event,
        EventStatus $to,
        string $outboxType,
        ?User $actor,
        ?string $ipAddress,
        bool $idempotent,
    ): Event {
        return $this->transactionRunner->run(function () use ($event, $to, $outboxType, $actor, $ipAddress, $idempotent): Event {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            if ($idempotent && $locked->status === $to) {
                return $locked->fresh(['category']);
            }

            $from = $locked->status;
            $this->stateMachine->assertCanTransition($from, $to);

            $oldValues = $this->eventSnapshot($locked);
            $locked->updateWithVersion(['status' => $to], $locked->version);
            $locked = $locked->fresh(['category']);

            $this->recordAudit(
                actor: $actor,
                entity: $locked,
                action: $to === EventStatus::Published ? 'published' : 'archived',
                oldValues: $oldValues,
                newValues: $this->eventSnapshot($locked),
                changedFields: ['status', 'version'],
                ipAddress: $ipAddress,
            );

            $this->outboxService->record(
                eventType: $outboxType,
                aggregate: $locked,
                payload: [
                    'from_status' => $from->value,
                    'to_status' => $to->value,
                    'version' => $locked->version,
                ],
            );

            return $locked;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function eventSnapshot(Event $event): array
    {
        return [
            'name' => $event->name,
            'slug' => $event->slug,
            'category_id' => $event->category_id,
            'status' => $event->status->value,
            'version' => $event->version,
            'start_datetime' => $event->start_datetime?->toIso8601String(),
            'end_datetime' => $event->end_datetime?->toIso8601String(),
        ];
    }

    private function resolveUniqueEventSlug(string $value, int $venueId, ?int $ignoreEventId = null): string
    {
        $base = Str::slug($value);

        if ($base === '') {
            $base = 'event';
        }

        $candidate = $base;
        $suffix = 1;

        while ($this->eventSlugExists($candidate, $venueId, $ignoreEventId)) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function eventSlugExists(string $slug, int $venueId, ?int $ignoreEventId): bool
    {
        return Event::query()
            ->where('venue_id', $venueId)
            ->where('slug', $slug)
            ->when($ignoreEventId !== null, fn ($query) => $query->where('id', '!=', $ignoreEventId))
            ->exists();
    }

    /**
     * @param  list<string>|null  $changedFields
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordAudit(
        ?User $actor,
        Event $entity,
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
