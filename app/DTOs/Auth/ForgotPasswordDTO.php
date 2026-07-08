<?php

namespace App\DTOs\Auth;

use App\DTOs\BaseDTO;

readonly class ForgotPasswordDTO extends BaseDTO
{
    public function __construct(
        public string $email,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            email: (string) $data['email'],
        );
    }

    public function toArray(): array
    {
        return [
            'email' => $this->email,
        ];
    }
}
