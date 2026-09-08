<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            SmsTemplateSeeder::class,
        ]);

        // A first administrator, so a fresh install is reachable.
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'مدیر سیستم',
                'full_name' => 'مدیر سیستم',
                'password' => 'password',
                'is_active' => true,
                'must_change_password' => true,
            ],
        );

        $admin->syncRoles(['admin']);

        if (app()->environment('local', 'testing')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
