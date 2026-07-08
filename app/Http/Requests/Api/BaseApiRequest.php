<?php

namespace App\Http\Requests\Api;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\DTOs\BaseDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use LogicException;

abstract class BaseApiRequest extends FormRequest
{
    /**
     * @return class-string<BaseDTO>|null
     */
    protected function dtoClass(): ?string
    {
        return null;
    }

    /**
     * Builds an `exists` validation rule scoped to the current tenant's venue,
     * so cross-venue IDs fail validation (422) instead of leaking through to a
     * 404 at the model-scope layer.
     */
    protected function tenantExists(string $table, string $column = 'id'): Exists
    {
        $venueId = app(TenantContextInterface::class)->requireVenueId();

        return Rule::exists($table, $column)->where('venue_id', $venueId);
    }

    public function toDto(): BaseDTO
    {
        $dtoClass = $this->dtoClass();

        if ($dtoClass === null) {
            throw new LogicException(static::class.' must override dtoClass() to use toDto().');
        }

        if (! is_subclass_of($dtoClass, BaseDTO::class)) {
            throw new LogicException("{$dtoClass} must extend ".BaseDTO::class.'.');
        }

        if (! method_exists($dtoClass, 'fromArray')) {
            throw new LogicException("{$dtoClass} must implement fromArray().");
        }

        /** @var BaseDTO $dto */
        $dto = $dtoClass::fromArray($this->validated());

        return $dto;
    }
}
