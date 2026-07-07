<?php

namespace App\Models;

use App\Support\Concerns\BelongsToVenue;
use Database\Factories\ApiClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiClient extends Model
{
    /** @use HasFactory<ApiClientFactory> */
    use BelongsToVenue, HasFactory;

    protected $fillable = [
        'venue_id',
        'name',
        'api_key',
        'secret',
        'scopes',
        'active',
        'expires_at',
        'last_used_at',
    ];

    protected $hidden = [
        'secret',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'active' => 'boolean',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
