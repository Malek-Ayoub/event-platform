<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RolePermission>
 */
class RolePermissionFactory extends Factory
{
    protected $model = RolePermission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role' => 'staff',
            'permission_id' => Permission::factory(),
        ];
    }

    public function forOwner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'owner',
        ]);
    }

    public function forStaff(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'staff',
        ]);
    }
}
