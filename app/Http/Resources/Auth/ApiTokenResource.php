<?php

namespace App\Http\Resources\Auth;

use App\DTOs\Auth\TokenResultDTO;
use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/** @mixin TokenResultDTO */
class ApiTokenResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TokenResultDTO $result */
        $result = $this->resource;

        return [
            'user' => new AuthenticatedUserResource($result->user),
            'token' => $result->plainTextToken,
            'token_type' => $result->tokenType,
        ];
    }
}
