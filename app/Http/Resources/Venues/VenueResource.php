<?php

namespace App\Http\Resources\Venues;

use App\Http\Resources\ApiResource;
use App\Models\Venue;
use Illuminate\Http\Request;

/** @mixin Venue */
class VenueResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'subdomain' => $this->subdomain,
            'status' => $this->status,
            'commission_rate' => $this->commission_rate,
            'owner' => [
                'name' => $this->owner?->name,
                'email' => $this->owner?->email,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
