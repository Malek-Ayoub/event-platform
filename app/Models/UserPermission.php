<?php

namespace App\Models;

use App\Support\Concerns\BelongsToVenue;
use Database\Factories\UserPermissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermission extends Model
{
    /** @use HasFactory<UserPermissionFactory> */
    use BelongsToVenue, HasFactory;

    protected $fillable = [
        'venue_id',
        'user_id',
        'permission_id',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'user_id' => 'integer',
            'permission_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
