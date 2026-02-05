<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perms = [
            'view-subscriptions', 'manage-subscriptions',
            'view-orders', 'manage-orders',
            'view-products', 'manage-products',
            'view-zones', 'manage-zones',
        ];

        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        $roles = [
            'admin'        => $perms,
            'zones-head'   => ['view-zones','manage-zones','view-orders'],
            'zones-director'=> ['view-zones','manage-zones'],
            'zone-manager' => ['view-zones','view-orders','manage-orders'],
            'vendor'       => ['view-orders'],
            'vendor-milk'  => ['view-orders'],
            'workman'      => ['view-orders'],
            'customer'     => ['view-subscriptions'],
        ];

        foreach ($roles as $role => $attachPerms) {
            $r = Role::firstOrCreate(['name' => $role]);
            $r->syncPermissions($attachPerms);
        }
    }
}
