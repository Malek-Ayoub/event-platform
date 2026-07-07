<?php

namespace App\Models;

use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'slug' => 'string',
            'description' => 'string',
        ];
    }

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }
}
