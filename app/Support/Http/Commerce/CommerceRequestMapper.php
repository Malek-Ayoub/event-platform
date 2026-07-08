<?php

namespace App\Support\Http\Commerce;

use App\DTOs\Commerce\CreateCouponDTO;
use App\DTOs\Commerce\CreateProductDTO;
use App\DTOs\Commerce\CreateProductVariantDTO;
use App\DTOs\Commerce\CreatePromoCodeDTO;
use App\DTOs\Commerce\UpdateCouponDTO;
use App\DTOs\Commerce\UpdateProductDTO;
use App\DTOs\Commerce\UpdateProductVariantDTO;
use App\DTOs\Commerce\UpdatePromoCodeDTO;
use App\Models\User;
use App\Services\Commerce\Data\CreateCouponData;
use App\Services\Commerce\Data\CreateProductData;
use App\Services\Commerce\Data\CreateProductVariantData;
use App\Services\Commerce\Data\CreatePromoCodeData;
use App\Services\Commerce\Data\UpdateCouponData;
use App\Services\Commerce\Data\UpdateProductData;
use App\Services\Commerce\Data\UpdateProductVariantData;
use App\Services\Commerce\Data\UpdatePromoCodeData;
use Carbon\CarbonImmutable;

class CommerceRequestMapper
{
    public static function toCreateProductData(CreateProductDTO $dto, ?User $actor, ?string $ipAddress): CreateProductData
    {
        return new CreateProductData(
            name: $dto->name,
            description: $dto->description,
            price: $dto->price,
            eventId: $dto->eventId,
            isActive: $dto->isActive,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }

    public static function toUpdateProductData(UpdateProductDTO $dto, ?User $actor, ?string $ipAddress): UpdateProductData
    {
        return new UpdateProductData(
            name: $dto->name,
            description: $dto->description,
            price: $dto->price,
            eventId: $dto->eventId,
            updateEventId: $dto->updateEventId,
            isActive: $dto->isActive,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }

    public static function toCreateProductVariantData(CreateProductVariantDTO $dto, ?User $actor, ?string $ipAddress): CreateProductVariantData
    {
        return new CreateProductVariantData(
            productId: $dto->productId,
            name: $dto->name,
            sku: $dto->sku,
            priceOverride: $dto->priceOverride,
            isActive: $dto->isActive,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }

    public static function toUpdateProductVariantData(UpdateProductVariantDTO $dto, ?User $actor, ?string $ipAddress): UpdateProductVariantData
    {
        return new UpdateProductVariantData(
            name: $dto->name,
            sku: $dto->sku,
            priceOverride: $dto->priceOverride,
            isActive: $dto->isActive,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }

    public static function toCreateCouponData(CreateCouponDTO $dto, ?User $actor, ?string $ipAddress): CreateCouponData
    {
        return new CreateCouponData(
            code: $dto->code,
            discountType: $dto->discountType,
            discountValue: $dto->discountValue,
            minOrderAmount: $dto->minOrderAmount,
            maxUses: $dto->maxUses,
            startsAt: self::parseDate($dto->startsAt),
            expiresAt: self::parseDate($dto->expiresAt),
            isActive: $dto->isActive,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }

    public static function toUpdateCouponData(UpdateCouponDTO $dto, ?User $actor, ?string $ipAddress): UpdateCouponData
    {
        return new UpdateCouponData(
            code: $dto->code,
            discountType: $dto->discountType,
            discountValue: $dto->discountValue,
            minOrderAmount: $dto->minOrderAmount,
            maxUses: $dto->maxUses,
            startsAt: self::parseDate($dto->startsAt),
            expiresAt: self::parseDate($dto->expiresAt),
            isActive: $dto->isActive,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }

    public static function toCreatePromoCodeData(CreatePromoCodeDTO $dto, ?User $actor, ?string $ipAddress): CreatePromoCodeData
    {
        return new CreatePromoCodeData(
            code: $dto->code,
            discountType: $dto->discountType,
            discountValue: $dto->discountValue,
            minOrderAmount: $dto->minOrderAmount,
            maxUses: $dto->maxUses,
            startsAt: self::parseDate($dto->startsAt),
            expiresAt: self::parseDate($dto->expiresAt),
            isActive: $dto->isActive,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }

    public static function toUpdatePromoCodeData(UpdatePromoCodeDTO $dto, ?User $actor, ?string $ipAddress): UpdatePromoCodeData
    {
        return new UpdatePromoCodeData(
            code: $dto->code,
            discountType: $dto->discountType,
            discountValue: $dto->discountValue,
            minOrderAmount: $dto->minOrderAmount,
            maxUses: $dto->maxUses,
            startsAt: self::parseDate($dto->startsAt),
            expiresAt: self::parseDate($dto->expiresAt),
            isActive: $dto->isActive,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }

    private static function parseDate(?string $value): ?\DateTimeInterface
    {
        return $value !== null ? CarbonImmutable::parse($value) : null;
    }
}
