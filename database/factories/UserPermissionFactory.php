<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPermission>
 */
class UserPermissionFactory extends Factory
{
    protected $model = UserPermission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'user_id' => User::factory(),
            'permission_id' => Permission::factory(),
        ];
    }
}
