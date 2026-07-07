<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;

class TokenService
{
    /**
     * @param  list<string>  $abilities
     */
    public function createToken(
        User $user,
        ?string $deviceName = null,
        array $abilities = ['*'],
    ): NewAccessToken {
        $name = $deviceName ?? (string) config('platform.auth.token_name', 'api');

        return $user->createToken($name, $abilities);
    }

    public function revokeCurrentToken(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token !== null) {
            $token->delete();
        }
    }

    public function revokeAllTokens(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
        });
    }
}
