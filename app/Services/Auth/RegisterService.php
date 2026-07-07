<?php

namespace App\Services\Auth;

use App\DTOs\Auth\RegisterDTO;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterService
{
    public function register(RegisterDTO $dto): User
    {
        return DB::transaction(function () use ($dto): User {
            return User::query()->create([
                'name' => $dto->name,
                'email' => $dto->email,
                'password' => $dto->password,
                'phone' => $dto->phone,
                'is_super_admin' => false,
            ]);
        });
    }
}
