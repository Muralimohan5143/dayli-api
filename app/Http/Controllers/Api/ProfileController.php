<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function saveServiceProfile(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email'     => 'nullable|email|max:255',
            'gender'    => 'nullable|string|max:20',
            'address'   => 'required|string|max:500',
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',

            // NEW
            'service_handle' => 'nullable|string',
            'subscription_type_id' => 'nullable|integer',
            'zone_id' => 'nullable|integer'
        ]);

        $user = $request->user(); // ✅ existing user from token

        DB::transaction(function () use ($user, $data) {

            // ✅ update user fields that exist
            $user->name = $data['full_name'];
            if (!empty($data['email'])) $user->email = $data['email'];
            if (!empty($data['gender'])) $user->gender = $data['gender'];

            // optional: you already have users.address column
            $user->address = $data['address'];

            $user->save();

            // ==========================
            // Delivery boy assignment
            // ==========================

            if (($data['service_handle'] ?? null) === 'workman-delivery-boy') {

                if (! $user->hasRole('workman-delivery-boy')) {
                    $user->assignRole('workman-delivery-boy');
                }

                DB::table('delivery_tasks')->updateOrInsert(
                    [
                        'delivery_exec_id' => $user->id,
                        'subscription_type_id' => $data['subscription_type_id'] ?? null,
                    ],
                    [
                        'delivery_task' => 'Delivery Assignment',
                        'zone_id' => $data['zone_id'] ?? 1,
                        'status' => 'today',
                        'start_date' => now()->toDateString(),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            // ✅ addresses table is polymorphic + has line1, lat, lng
            DB::table('addresses')->updateOrInsert(
                [
                    'addressable_type' => User::class,
                    'addressable_id'   => $user->id,
                    'is_default'       => 1,
                ],
                [
                    'line1'      => $data['address'],
                    'lat'        => $data['latitude'] ?? null,
                    'lng'        => $data['longitude'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        });

        return response()->json([
            'message' => 'Profile saved',
        ]);
    }
}
