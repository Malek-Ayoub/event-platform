<?php

namespace App\Support\Http\Events;

use App\DTOs\Events\CreateCategoryDTO;
use App\DTOs\Events\CreateEventDTO;
use App\DTOs\Events\CreateTicketTypeDTO;
use App\DTOs\Events\UpdateCategoryDTO;
use App\DTOs\Events\UpdateEventDTO;
use App\DTOs\Events\UpdateTicketTypeDTO;
use App\Models\User;
use App\Services\Events\Data\CreateCategoryData;
use App\Services\Events\Data\CreateEventData;
use App\Services\Events\Data\CreateTicketTypeData;
use App\Services\Events\Data\UpdateCategoryData;
use App\Services\Events\Data\UpdateEventData;
use App\Services\Events\Data\UpdateTicketTypeData;
use Carbon\CarbonImmutable;

class EventRequestMapper
{
    public static function toCreateEventData(CreateEventDTO $dto, ?User $actor, ?string $ipAddress): CreateEventData
    {
        return new CreateEventData(
            name: $dto->name,
            slug: $dto->slug,
            categoryId: $dto->categoryId,
            description: $dto->description,
            bannerUrl: $dto->bannerUrl,
            gallery: $dto->gallery,
            videoUrl: $dto->videoUrl,
            djInfo: $dto->djInfo,
            startDatetime: self::parseDate($dto->startDatetime),
            endDatetime: self::parseDate($dto->endDatetime),
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }

    public static function toUpdateEventData(UpdateEventDTO $dto, ?User $actor, ?string $ipAddress): UpdateEventData
    {
        return new UpdateEventData(
            expectedVersion: $dto->version,
            name: $dto->name,
            slug: $dto->slug,
            categoryId: $dto->categoryId,
            description: $dto->description,
            bannerUrl: $dto->bannerUrl,
            gallery: $dto->gallery,
            videoUrl: $dto->videoUrl,
            djInfo: $dto->djInfo,
            startDatetime: self::parseDate($dto->startDatetime),
            endDatetime: self::parseDate($dto->endDatetime),
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }

    public static function toCreateCategoryData(CreateCategoryDTO $dto, ?User $actor, ?string $ipAddress): CreateCategoryData
    {
        return new CreateCategoryData(
            name: $dto->name,
            slug: $dto->slug,
            description: $dto->description,
            sortOrder: $dto->sortOrder,
            isActive: $dto->isActive,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }

    public static function toUpdateCategoryData(UpdateCategoryDTO $dto, ?User $actor, ?string $ipAddress): UpdateCategoryData
    {
        return new UpdateCategoryData(
            name: $dto->name,
            slug: $dto->slug,
            description: $dto->description,
            sortOrder: $dto->sortOrder,
            isActive: $dto->isActive,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }

    public static function toCreateTicketTypeData(CreateTicketTypeDTO $dto, ?User $actor, ?string $ipAddress): CreateTicketTypeData
    {
        return new CreateTicketTypeData(
            eventId: $dto->eventId,
            name: $dto->name,
            price: $dto->price,
            quantity: $dto->quantity,
            saleStart: self::parseDate($dto->saleStart),
            saleEnd: self::parseDate($dto->saleEnd),
            benefits: $dto->benefits,
            color: $dto->color,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }

    public static function toUpdateTicketTypeData(UpdateTicketTypeDTO $dto, ?User $actor, ?string $ipAddress): UpdateTicketTypeData
    {
        return new UpdateTicketTypeData(
            expectedVersion: $dto->version,
            name: $dto->name,
            price: $dto->price,
            quantity: $dto->quantity,
            saleStart: self::parseDate($dto->saleStart),
            saleEnd: self::parseDate($dto->saleEnd),
            benefits: $dto->benefits,
            color: $dto->color,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }

    private static function parseDate(?string $value): ?\DateTimeInterface
    {
        return $value !== null ? CarbonImmutable::parse($value) : null;
    }
}
