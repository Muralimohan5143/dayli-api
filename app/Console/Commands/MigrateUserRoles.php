<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class MigrateUserRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-user-roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::all();

        foreach ($users as $user) {
            if ($user->email === 'admin@dayli.in') {
                $user->assignRole('admin');
            } elseif ($user->zone_id === 2) {
                $user->assignRole('zone-manager');
            } else {
                $user->assignRole('customer');
            }
        }
    }
}
