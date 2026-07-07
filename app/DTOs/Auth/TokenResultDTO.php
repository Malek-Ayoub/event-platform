<?php

namespace App\DTOs\Auth;

use App\DTOs\BaseDTO;
use App\Models\User;

readonly class TokenResultDTO extends BaseDTO
{
    public function __construct(
        public User $user,
        public string $plainTextToken,
        public string $tokenType = 'Bearer',
    ) {}

    public function toArray(): array
    {
        return [
            'token' => $this->plainTextToken,
            'token_type' => $this->tokenType,
        ];
    }
}
