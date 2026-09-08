<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (Permissions::roles() as $roleName => $granted) {
            $role = Role::findOrCreate($roleName, 'web');

            // admin is granted everything through a Gate::before rule, so it
            // deliberately holds no explicit permissions.
            $role->syncPermissions($roleName === 'admin' ? [] : $granted);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
