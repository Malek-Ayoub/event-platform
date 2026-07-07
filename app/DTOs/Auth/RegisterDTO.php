<?php

namespace App\DTOs\Auth;

use App\DTOs\BaseDTO;

readonly class RegisterDTO extends BaseDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $phone = null,
        public ?string $deviceName = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            email: (string) $data['email'],
            password: (string) $data['password'],
            phone: isset($data['phone']) ? (string) $data['phone'] : null,
            deviceName: isset($data['device_name']) ? (string) $data['device_name'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'device_name' => $this->deviceName,
        ];
    }
}
