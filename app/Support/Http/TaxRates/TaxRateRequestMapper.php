<?php

namespace App\Support\Http\TaxRates;

use App\DTOs\TaxRates\CreateTaxRateDTO;
use App\DTOs\TaxRates\UpdateTaxRateDTO;
use App\Models\User;
use App\Services\TaxRates\Data\CreateTaxRateData;
use App\Services\TaxRates\Data\UpdateTaxRateData;

class TaxRateRequestMapper
{
    public static function toCreateTaxRateData(CreateTaxRateDTO $dto, ?User $actor, ?string $ipAddress): CreateTaxRateData
    {
        return new CreateTaxRateData(
            name: $dto->name,
            rate: $dto->rate,
            isActive: $dto->isActive,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }

    public static function toUpdateTaxRateData(UpdateTaxRateDTO $dto, ?User $actor, ?string $ipAddress): UpdateTaxRateData
    {
        return new UpdateTaxRateData(
            expectedVersion: $dto->version,
            name: $dto->name,
            rate: $dto->rate,
            isActive: $dto->isActive,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }
}
