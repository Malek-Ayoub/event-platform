<?php

namespace App\Http\Requests\Settlements;

use App\Models\Venue;

class AdminVenueSettlementRequest extends SettlementReadRequest
{
    public function authorize(): bool
    {
        if (! ($this->user()?->isSuperAdmin() ?? false)) {
            return false;
        }

        return Venue::query()->whereKey($this->routeVenueId())->exists();
    }

    public function routeVenueId(): int
    {
        return (int) $this->route('venue');
    }

    public function page(): int
    {
        return max(1, (int) ($this->validated('page') ?? 1));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
    }
}
