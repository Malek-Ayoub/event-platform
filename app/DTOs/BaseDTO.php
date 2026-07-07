<?php

namespace App\DTOs;

abstract readonly class BaseDTO
{
    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;
}
