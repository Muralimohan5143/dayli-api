<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserWhitelist;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SeedUserWhitelistsFromUsersSeeder extends Seeder
{
    public function run(): void
    {
        $totalUsers = 0;
        $whitelistCreatedOrUpdated = 0;
        $rolelessUsersSeededAsCustomer = 0;
        $usersWithRolesProcessed = 0;

        User::query()
            ->with('roles')
            ->select('id', 'name', 'email', 'phone', 'zone_id')
            ->orderBy('id')
            ->chunk(500, function ($users) use (
                &$totalUsers,
                &$whitelistCreatedOrUpdated,
                &$rolelessUsersSeededAsCustomer,
                &$usersWithRolesProcessed
            ) {
                foreach ($users as $user) {
                    $totalUsers++;

                    $roles = $user->roles;

                    // If user has no roles, seed as customer
                    if ($roles->isEmpty()) {
                        UserWhitelist::updateOrCreate(
                            [
                                'phone' => $user->phone,
                                'role'  => 'customer',
                            ],
                            [
                                'name'        => $user->name,
                                'email'       => $user->email,
                                'zone_id'     => $user->zone_id,
                                'is_active'   => true,
                                'approved_at' => Carbon::now(),
                                'meta'        => [
                                    'source' => 'users_table',
                                    'user_id' => $user->id,
                                    'seed_reason' => 'user_has_no_roles_so_seeded_as_customer',
                                ],
                            ]
                        );

                        $rolelessUsersSeededAsCustomer++;
                        $whitelistCreatedOrUpdated++;

                        $this->command?->info(
                            "Seeded whitelist as customer | user_id={$user->id} | phone={$user->phone}"
                        );

                        continue;
                    }

                    // If user has roles, seed each role into whitelist
                    foreach ($roles as $role) {
                        UserWhitelist::updateOrCreate(
                            [
                                'phone' => $user->phone,
                                'role'  => $role->name,
                            ],
                            [
                                'name'        => $user->name,
                                'email'       => $user->email,
                                'zone_id'     => $user->zone_id,
                                'is_active'   => true,
                                'approved_at' => Carbon::now(),
                                'meta'        => [
                                    'source' => 'users_table',
                                    'user_id' => $user->id,
                                    'seed_reason' => 'copied_from_existing_user_roles',
                                ],
                            ]
                        );

                        $whitelistCreatedOrUpdated++;

                        $this->command?->line(
                            "Seeded whitelist | user_id={$user->id} | phone={$user->phone} | role={$role->name}"
                        );
                    }

                    $usersWithRolesProcessed++;
                }
            });

        $this->command?->newLine();
        $this->command?->info('Done');
        $this->command?->line("Total users checked: {$totalUsers}");
        $this->command?->line("Users with existing roles processed: {$usersWithRolesProcessed}");
        $this->command?->line("Roleless users seeded as customer: {$rolelessUsersSeededAsCustomer}");
        $this->command?->line("Whitelist rows created/updated: {$whitelistCreatedOrUpdated}");
    }
}
