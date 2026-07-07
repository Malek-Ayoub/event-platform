<?php

namespace App\DTOs\Auth;

use App\DTOs\BaseDTO;

readonly class LoginDTO extends BaseDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public ?string $deviceName = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            email: (string) $data['email'],
            password: (string) $data['password'],
            deviceName: isset($data['device_name']) ? (string) $data['device_name'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'device_name' => $this->deviceName,
        ];
    }
}
