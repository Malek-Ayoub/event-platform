<?php

namespace App\Http\Requests\Api;

use App\DTOs\BaseDTO;
use Illuminate\Foundation\Http\FormRequest;
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
