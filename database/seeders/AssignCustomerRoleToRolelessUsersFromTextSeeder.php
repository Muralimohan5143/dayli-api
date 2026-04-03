<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

class AssignCustomerRoleToRolelessUsersFromTextSeeder extends Seeder
{
    public function run(): void
    {
        $path = storage_path('app/imports/users_export.txt');

        if (!File::exists($path)) {
            $this->command?->error("File not found: {$path}");
            return;
        }

        // Ensure customer role exists
        Role::findOrCreate('customer', 'web');

        $content = File::get($path);
        $lines = preg_split("/\r\n|\n|\r/", $content);

        $totalLines = 0;
        $phonesFound = 0;
        $usersMatched = 0;
        $rolesAssigned = 0;
        $alreadyHadRoles = 0;
        $usersNotFound = 0;
        $duplicatePhonesSkipped = 0;

        $seenPhones = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $totalLines++;

            // file rows are tab-separated
            $cols = preg_split('/\t+/', $line);

            if (!is_array($cols) || count($cols) < 2) {
                continue;
            }

            $phone = $this->extractPhone($cols);

            if (!$phone) {
                continue;
            }

            $phonesFound++;

            // avoid processing same phone again from repeated rows
            if (isset($seenPhones[$phone])) {
                $duplicatePhonesSkipped++;
                continue;
            }
            $seenPhones[$phone] = true;

            $user = User::query()
                ->whereRaw("
        RIGHT(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', ''), '(', ''), ')', ''),
            10
        ) = ?
    ", [$phone])
                ->first();
            if (!$user) {
                $usersNotFound++;
                $this->command?->warn("User not found for phone: {$phone}");
                continue;
            }

            $usersMatched++;

            // IMPORTANT:
            // if user already has ANY role, do nothing
            if ($user->roles()->exists()) {
                $alreadyHadRoles++;
                $this->command?->line("Skipped (already has role) | user_id={$user->id} | phone={$phone}");
                continue;
            }

            // assign customer only if no roles at all
            $user->assignRole('customer');
            $rolesAssigned++;

            $this->command?->info("Assigned customer role | user_id={$user->id} | phone={$phone}");
        }

        $this->command?->newLine();
        $this->command?->info("Done.");
        $this->command?->line("Total lines: {$totalLines}");
        $this->command?->line("Phones found: {$phonesFound}");
        $this->command?->line("Matched users: {$usersMatched}");
        $this->command?->line("Customer roles assigned: {$rolesAssigned}");
        $this->command?->line("Already had roles: {$alreadyHadRoles}");
        $this->command?->line("Users not found: {$usersNotFound}");
        $this->command?->line("Duplicate phones skipped: {$duplicatePhonesSkipped}");
    }

    private function extractPhone(array $cols): ?string
    {
        foreach ($cols as $col) {
            $value = trim((string) $col);

            if ($value === '') {
                continue;
            }

            // keep digits only
            $digits = preg_replace('/\D+/', '', $value);

            // 10-digit Indian local number
            if (strlen($digits) === 10) {
                return $digits;
            }

            // +91XXXXXXXXXX or 91XXXXXXXXXX
            if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
                return substr($digits, -10);
            }
        }

        return null;
    }
}
