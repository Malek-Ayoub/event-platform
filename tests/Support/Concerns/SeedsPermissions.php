<?php

namespace Tests\Support\Concerns;

use Database\Seeders\PermissionSeeder;

trait SeedsPermissions
{
    protected function seedPermissionsCatalog(): void
    {
        $this->seed(PermissionSeeder::class);
    }
}
