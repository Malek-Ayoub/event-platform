<?php

namespace App\DTOs\Auth;

use App\DTOs\BaseDTO;

readonly class ChangePasswordDTO extends BaseDTO
{
    public function __construct(
        public string $currentPassword,
        public string $password,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            currentPassword: (string) $data['current_password'],
            password: (string) $data['password'],
        );
    }

    public function toArray(): array
    {
        return [];
    }
}
