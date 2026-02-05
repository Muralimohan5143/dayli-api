<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class MilkCustomersWithAddressesSeeder extends Seeder
{
    public function run(): void
    {
        $zoneId = 1; // Zone #1 (Kurnool Checkpost for you)
        $hasCountry = Schema::hasColumn('addresses', 'country');

        // Ensure roles
        $customerRole = Role::firstOrCreate(['name' => 'customer']);

        // --- paste your generated $rows array here (from earlier) ---
        $rows = []; // keep your big array; omitted here for brevity

        // Sanitizers
        $clean = function ($s) {
            if ($s === null) return null;
            $s = preg_replace('/[\x{0C00}-\x{0C7F}\r\n]+/u', ' ', $s); // remove Telugu block + newlines
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/\s*,\s*$/', '', trim($s));
            return $s === '' ? null : $s;
        };
        $normPhone = function ($p) {
            if ($p === null) return null;
            $digits = preg_replace('/[^\d+]/', '', $p);
            if ($digits === '' || in_array($digits, ['1','2','999999999'], true)) return null;
            return $digits;
        };

        // Clean rows
        $rows = array_map(function($r) use ($clean, $normPhone, $zoneId) {
            $r['name']       = $clean($r['name'] ?? null);
            $r['first_name'] = $clean($r['first_name'] ?? null);
            $r['last_name']  = $clean($r['last_name'] ?? null);
            $r['phone']      = $normPhone($r['phone'] ?? null);
            $r['email']      = $clean($r['email'] ?? null);
            $r['line1']      = $clean($r['line1'] ?? null);
            $r['line2']      = $clean($r['line2'] ?? null);
            $r['nagar']      = $clean($r['nagar'] ?? null);
            $r['city']       = $clean($r['city'] ?? 'Kurnool');
            $r['state']      = $clean($r['state'] ?? 'Andhra Pradesh');
            $r['pincode']    = $clean($r['pincode'] ?? '518002');
            $r['zone_id']    = $r['zone_id'] ?? $zoneId;

            if (empty($r['name'])) {
                $parts = array_filter([$r['first_name'] ?? null, $r['last_name'] ?? null]);
                $r['name'] = $parts ? implode(' ', $parts) : 'Customer';
            }
            return $r;
        }, $rows);

        foreach ($rows as $row) {
            // Upsert user (phone → email → name+zone)
            $query = DB::table('users');
            $byPhone = !empty($row['phone']) && strlen(preg_replace('/\D/','',$row['phone'])) >= 7;
            if ($byPhone) {
                $query->where('phone', $row['phone']);
            } elseif (!empty($row['email'])) {
                $query->where('email', $row['email']);
            } else {
                $query->where('name', $row['name'])->where('zone_id', $row['zone_id']);
            }
            $existing = $query->first();

            if ($existing) {
                DB::table('users')->where('id', $existing->id)->update([
                    'name'       => $row['name'] ?? $existing->name,
                    'first_name' => $row['first_name'] ?? $existing->first_name,
                    'last_name'  => $row['last_name'] ?? $existing->last_name,
                    'phone'      => $row['phone'] ?? $existing->phone,
                    'email'      => $row['email'] ?? $existing->email,
                    'zone_id'    => $row['zone_id'] ?? $existing->zone_id,
                    'updated_at' => now(),
                ]);
                $userId = $existing->id;
            } else {
                $userId = DB::table('users')->insertGetId([
                    'name'       => $row['name'],
                    'first_name' => $row['first_name'],
                    'last_name'  => $row['last_name'],
                    'phone'      => $row['phone'],
                    'email'      => $row['email'],
                    'zone_id'    => $row['zone_id'],
                    'password'   => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Ensure 'customer' role
            $existsRole = DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $userId)
                ->where('role_id', $customerRole->id)
                ->exists();
            if (!$existsRole) {
                DB::table('model_has_roles')->insert([
                    'role_id'    => $customerRole->id,
                    'model_type' => 'App\\Models\\User',
                    'model_id'   => $userId,
                ]);
            }

            // Ensure default address exists; create or update
            $addr = DB::table('addresses')
                ->where('addressable_type', 'App\\Models\\User')
                ->where('addressable_id', $userId)
                ->where('is_default', true)
                ->first();

            $payload = [
                'addressable_type' => 'App\\Models\\User',
                'addressable_id'   => $userId,
                'zone_id'          => $row['zone_id'],
                'line1'            => $row['line1'] ?? null,
                'line2'            => $row['line2'] ?? null,
                'nagar'            => $row['nagar'] ?? 'Unknown Nagar',
                'city'             => $row['city'] ?? 'Kurnool',
                'state'            => $row['state'] ?? 'Andhra Pradesh',
                'pincode'          => $row['pincode'] ?? '518002',
                'is_default'       => true,
                'updated_at'       => now(),
            ];
            if ($hasCountry) $payload['country'] = 'India';

            if ($addr) {
                DB::table('addresses')->where('id', $addr->id)->update($payload);
            } else {
                $payload['created_at'] = now();
                DB::table('addresses')->insert($payload);
            }
        }
    }
}
