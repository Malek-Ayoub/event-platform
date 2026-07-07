<?php

namespace App\Events\Permissions;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PermissionRevoked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $actor,
        public User $target,
        public Permission $permission,
        public int $venueId,
    ) {}
}
