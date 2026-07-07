<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('platform.seed.super_admin_email', 'superadmin@event-platform.test');

        $exists = DB::table('users')->where('email', $email)->exists();

        if ($exists) {
            DB::table('users')
                ->where('email', $email)
                ->update([
                    'is_super_admin' => true,
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('users')->insert([
            'name' => 'Super Admin',
            'email' => $email,
            'password' => Hash::make((string) config('platform.seed.super_admin_password', 'ChangeMeNow!123')),
            'phone' => null,
            'is_super_admin' => true,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
