<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $perms = [
            'view-subscriptions','manage-subscriptions',
            'view-users','manage-users',
            'view-vendors','manage-vendors',
            'view-zones','manage-zones',
        ];
        foreach ($perms as $p) Permission::firstOrCreate(['name' => $p]);

        $roles = [
            'admin','zones-director','zones-head','zone-manager',
            'vendor','vendor-milk',
            'workman','workman-delivery-boy',
            'customer',
        ];
        foreach ($roles as $r) Role::firstOrCreate(['name' => $r]);

        // admin gets all
        if ($admin = Role::where('name','admin')->first()) {
            $admin->syncPermissions(Permission::all());
        }
    }
}
