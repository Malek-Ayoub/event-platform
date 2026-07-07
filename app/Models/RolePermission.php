<?php

namespace App\Models;

use Database\Factories\RolePermissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePermission extends Model
{
    /** @use HasFactory<RolePermissionFactory> */
    use HasFactory;

    protected $fillable = [
        'role',
        'permission_id',
    ];

    protected function casts(): array
    {
        return [
            'role' => 'string',
            'permission_id' => 'integer',
        ];
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
