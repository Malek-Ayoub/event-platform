<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class VenueUser extends Pivot
{
    protected $table = 'venue_user';

    public $incrementing = true;

    protected $fillable = [
        'venue_id',
        'user_id',
        'role',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }
}
