<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\Events\Data\CreateTicketTypeData;
use App\Services\Events\Data\UpdateTicketTypeData;
use App\Services\OutboxService;
use App\Services\TransactionRunner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TicketTypeService
{
    public function __construct(
        private TransactionRunner $transactionRunner,
        private ActivityLogService $activityLogService,
        private OutboxService $outboxService,
    ) {}

    public function listForEvent(Event $event, int $perPage = 15): LengthAwarePaginator
    {
        return TicketType::query()
            ->where('event_id', $event->id)
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function createTicketType(CreateTicketTypeData $data): TicketType
    {
        return $this->transactionRunner->run(function () use ($data): TicketType {
            $event = Event::query()->findOrFail($data->eventId);

            $ticketType = TicketType::query()->create([
                'venue_id' => $event->venue_id,
                'event_id' => $event->id,
                'name' => $data->name,
                'price' => $data->price,
                'quantity' => $data->quantity,
                'quantity_sold' => 0,
                'sale_start' => $data->saleStart,
                'sale_end' => $data->saleEnd,
                'benefits' => $data->benefits,
                'color' => $data->color,
                'version' => 1,
            ]);

            $this->recordAudit($data->actor, $ticketType, 'created', null, $this->snapshot($ticketType), array_keys($this->snapshot($ticketType)), $data->ipAddress);

            $this->outboxService->record(
                eventType: 'ticket_type.created',
                aggregate: $ticketType,
                payload: [
                    'event_id' => $ticketType->event_id,
                    'name' => $ticketType->name,
                    'price' => $ticketType->price,
                    'quantity' => $ticketType->quantity,
                ],
            );

            return $ticketType->fresh();
        });
    }

    public function updateTicketType(TicketType $ticketType, UpdateTicketTypeData $data): TicketType
    {
        return $this->transactionRunner->run(function () use ($ticketType, $data): TicketType {
            $locked = TicketType::query()->whereKey($ticketType->id)->lockForUpdate()->firstOrFail();
            $oldValues = $this->snapshot($locked);
            $attributes = [];
            $changedFields = [];

            if ($data->name !== null) {
                $attributes['name'] = $data->name;
                $changedFields[] = 'name';
            }

            if ($data->price !== null) {
                $attributes['price'] = $data->price;
                $changedFields[] = 'price';
            }

            if ($data->quantity !== null) {
                $attributes['quantity'] = $data->quantity;
                $changedFields[] = 'quantity';
            }

            if ($data->saleStart !== null) {
                $attributes['sale_start'] = $data->saleStart;
                $changedFields[] = 'sale_start';
            }

            if ($data->saleEnd !== null) {
                $attributes['sale_end'] = $data->saleEnd;
                $changedFields[] = 'sale_end';
            }

            if ($data->benefits !== null) {
                $attributes['benefits'] = $data->benefits;
                $changedFields[] = 'benefits';
            }

            if ($data->color !== null) {
                $attributes['color'] = $data->color;
                $changedFields[] = 'color';
            }

            if ($changedFields === []) {
                return $locked;
            }

            $locked->updateWithVersion($attributes, $data->expectedVersion);
            $locked = $locked->fresh();

            $this->recordAudit($data->actor, $locked, 'updated', $oldValues, $this->snapshot($locked), [...$changedFields, 'version'], $data->ipAddress);

            $this->outboxService->record(
                eventType: 'ticket_type.updated',
                aggregate: $locked,
                payload: [
                    'event_id' => $locked->event_id,
                    'version' => $locked->version,
                    'changed_fields' => $changedFields,
                ],
            );

            return $locked;
        });
    }

    public function deleteTicketType(TicketType $ticketType, ?User $actor = null, ?string $ipAddress = null): void
    {
        $this->transactionRunner->run(function () use ($ticketType, $actor, $ipAddress): void {
            $locked = TicketType::query()->whereKey($ticketType->id)->lockForUpdate()->firstOrFail();
            $oldValues = $this->snapshot($locked);

            $locked->delete();

            $this->recordAudit($actor, $locked, 'deleted', $oldValues, null, ['deleted_at'], $ipAddress);

            $this->outboxService->record(
                eventType: 'ticket_type.deleted',
                aggregate: $locked,
                payload: ['event_id' => $locked->event_id, 'name' => $locked->name],
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(TicketType $ticketType): array
    {
        return [
            'event_id' => $ticketType->event_id,
            'name' => $ticketType->name,
            'price' => $ticketType->price,
            'quantity' => $ticketType->quantity,
            'quantity_sold' => $ticketType->quantity_sold,
            'version' => $ticketType->version,
        ];
    }

    /**
     * @param  list<string>|null  $changedFields
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordAudit(
        ?User $actor,
        TicketType $entity,
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
