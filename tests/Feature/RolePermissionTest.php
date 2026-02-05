<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\PermissionsSeeder;
use Spatie\Permission\Models\Role;




class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions before each test
        $this->seed(RolesAndPermissionsSeeder::class);
         $this->seed(PermissionsSeeder::class);
    }

    public function test_admin_has_all_permissions()
    {


        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue($admin->hasPermissionTo('view dashboard'));
        $this->assertTrue($admin->hasPermissionTo('manage change requests'));
        $this->assertTrue($admin->hasPermissionTo('edit users')); // Optional: test more
    }

    public function test_zone_manager_has_limited_permissions()
    {
        $manager = User::factory()->create();
        $manager->assignRole('zone-manager');

        $this->assertTrue($manager->hasPermissionTo('manage change requests'));
        $this->assertFalse($manager->hasPermissionTo('edit users'));
    }

    public function test_customer_has_no_permissions()
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->assertFalse($customer->hasPermissionTo('view dashboard'));
        $this->assertFalse($customer->hasPermissionTo('manage change requests'));
    }
}
