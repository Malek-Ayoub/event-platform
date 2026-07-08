<?php

namespace App\Support\Http;

use App\DTOs\BaseDTO;

trait MapsDtoFromRequest
{
    /**
     * @param  class-string<BaseDTO>  $dtoClass
     */
    protected function mapToDto(string $dtoClass, array $data): BaseDTO
    {
        if (! is_subclass_of($dtoClass, BaseDTO::class)) {
            throw new \InvalidArgumentException("{$dtoClass} must extend ".BaseDTO::class.'.');
        }

        if (! method_exists($dtoClass, 'fromArray')) {
            throw new \InvalidArgumentException("{$dtoClass} must implement fromArray().");
        }

        /** @var BaseDTO $dto */
        $dto = $dtoClass::fromArray($data);

        return $dto;
    }
}
