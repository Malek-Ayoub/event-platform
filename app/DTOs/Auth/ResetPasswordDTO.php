<?php

namespace App\DTOs\Auth;

use App\DTOs\BaseDTO;

readonly class ResetPasswordDTO extends BaseDTO
{
    public function __construct(
        public string $email,
        public string $token,
        public string $password,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            email: (string) $data['email'],
            token: (string) $data['token'],
            password: (string) $data['password'],
        );
    }

    public function toArray(): array
    {
        return [
            'email' => $this->email,
        ];
    }
}
