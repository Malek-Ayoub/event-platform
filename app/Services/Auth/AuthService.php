<?php

namespace App\Services\Auth;

use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterDTO;
use App\DTOs\Auth\TokenResultDTO;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuthService
{
    public function __construct(
        private readonly LoginService $loginService,
        private readonly RegisterService $registerService,
        private readonly TokenService $tokenService,
    ) {}

    public function login(LoginDTO $dto, ?int $venueId = null): TokenResultDTO
    {
        $user = $venueId === null
            ? $this->loginService->attempt($dto)
            : $this->loginService->attemptForVenue($dto, $venueId);

        return $this->issueTokenForUser($user, $dto->deviceName);
    }

    public function register(RegisterDTO $dto): TokenResultDTO
    {
        $user = $this->registerService->register($dto);

        return $this->issueTokenForUser($user, $dto->deviceName);
    }

    public function logout(User $user): void
    {
        $this->tokenService->revokeCurrentToken($user);
    }

    public function logoutAll(User $user): void
    {
        $this->tokenService->revokeAllTokens($user);
    }

    private function issueTokenForUser(User $user, ?string $deviceName): TokenResultDTO
    {
        return DB::transaction(function () use ($user, $deviceName): TokenResultDTO {
            $token = $this->tokenService->createToken($user, $deviceName);

            return new TokenResultDTO(
                user: $user->fresh(),
                plainTextToken: $token->plainTextToken,
            );
        });
    }
}
