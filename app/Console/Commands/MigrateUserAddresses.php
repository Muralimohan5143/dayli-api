<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Address;

class MigrateUserAddresses extends Command
{
    protected $signature = 'migrate:users-to-addresses';
    protected $description = 'Migrate address fields from users table into addresses table';

    public function handle(): void
    {
        $users = User::whereNotNull('first_address')->orWhereNotNull('second_address')->get();

        foreach ($users as $user) {
            Address::create([
                'user_id' => $user->id,
                'label' => 'Imported',
                'line1' => $user->first_address ?? '-',
                'line2' => $user->second_address ?? null,
                'city' => $user->city ?? '-',
                'state' => $user->state ?? '-',
                'pincode' => $user->zip ?? '-',
                'nagar' => $user->location ?? null,
                'is_default' => true
            ]);
        }

        $this->info("✅ Migrated {$users->count()} user addresses to addresses table.");
    }
}
