<?php

namespace App\Services\Auth;

use App\DTOs\Auth\LoginDTO;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\UserNotInVenueException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginService
{
    public function attempt(LoginDTO $dto): User
    {
        $user = User::query()->where('email', $dto->email)->first();

        if ($user === null || ! Hash::check($dto->password, $user->password)) {
            throw new InvalidCredentialsException;
        }

        return $user;
    }

    public function attemptForVenue(LoginDTO $dto, int $venueId): User
    {
        $user = $this->attempt($dto);

        if (! $user->belongsToVenue($venueId)) {
            throw new UserNotInVenueException;
        }

        return $user;
    }
}
