<?php

namespace Database\Seeders;

use App\Services\Authorization\PermissionGateRegistrar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * @return list<array{name: string, slug: string, description: string}>
     */
    public static function catalog(): array
    {
        return [
            ['name' => 'Manage Events', 'slug' => 'events.manage', 'description' => 'Create, update, and publish events.'],
            ['name' => 'Manage Ticket Types', 'slug' => 'ticket_types.manage', 'description' => 'Manage ticket types and pricing.'],
            ['name' => 'Manage Orders', 'slug' => 'orders.manage', 'description' => 'View and manage orders.'],
            ['name' => 'Process Refunds', 'slug' => 'refunds.process', 'description' => 'Initiate and process refunds.'],
            ['name' => 'Check In Tickets', 'slug' => 'checkin.perform', 'description' => 'Perform ticket check-in.'],
            ['name' => 'Manage Seating', 'slug' => 'seating.manage', 'description' => 'Manage zones, tables, and seats.'],
            ['name' => 'Manage Reservations', 'slug' => 'reservations.manage', 'description' => 'Manage table reservations.'],
            ['name' => 'Manage Products', 'slug' => 'products.manage', 'description' => 'Manage products and variants.'],
            ['name' => 'Manage Discounts', 'slug' => 'discounts.manage', 'description' => 'Manage coupons and promo codes.'],
            ['name' => 'Manage Staff Permissions', 'slug' => 'permissions.manage', 'description' => 'Grant or revoke staff permissions.'],
            ['name' => 'Manage Venue Settings', 'slug' => 'venue.settings.manage', 'description' => 'Manage venue configuration and theme.'],
            ['name' => 'View Reports', 'slug' => 'reports.view', 'description' => 'View venue financial and operational reports.'],
            ['name' => 'Manage API Clients', 'slug' => 'api_clients.manage', 'description' => 'Manage third-party API clients.'],
            ['name' => 'Manage Templates', 'slug' => 'templates.manage', 'description' => 'Manage email and SMS templates.'],
        ];
    }

    public function run(): void
    {
        $now = now();

        foreach (self::catalog() as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                [
                    'name' => $permission['name'],
                    'description' => $permission['description'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        $ownerPermissions = DB::table('permissions')->pluck('id', 'slug');

        foreach ($ownerPermissions as $slug => $permissionId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role' => 'owner', 'permission_id' => $permissionId],
                ['updated_at' => $now, 'created_at' => $now],
            );
        }

        $staffDefaults = [
            'checkin.perform',
            'reservations.manage',
            'orders.manage',
            'reports.view',
        ];

        foreach ($staffDefaults as $slug) {
            if (! isset($ownerPermissions[$slug])) {
                continue;
            }

            DB::table('role_permissions')->updateOrInsert(
                ['role' => 'staff', 'permission_id' => $ownerPermissions[$slug]],
                ['updated_at' => $now, 'created_at' => $now],
            );
        }

        app(PermissionGateRegistrar::class)->registerAll();
    }
}
