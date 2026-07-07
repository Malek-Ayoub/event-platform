<?php

namespace App\Http\Resources;

use App\DTOs\Auth\TokenResultDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TokenResultDTO */
class TokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TokenResultDTO $result */
        $result = $this->resource;

        return [
            'user' => new UserResource($result->user),
            'token' => $result->plainTextToken,
            'token_type' => $result->tokenType,
        ];
    }
}
