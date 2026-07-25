<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Vendor / Workman Profile
    |--------------------------------------------------------------------------
    */

    public function saveServiceProfile(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email'     => 'nullable|email|max:255',
            'gender'    => 'nullable|string|max:20',
            'address'   => 'required|string|max:500',
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',

            'service_handle'      => 'nullable|string|max:100',
            'subscription_type_id' => 'nullable|integer',
            'zone_id'              => 'nullable|integer',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user, $data) {
            $user->name = $data['full_name'];

            if (!empty($data['email'])) {
                $user->email = $data['email'];
            }

            if (!empty($data['gender'])) {
                $user->gender = $data['gender'];
            }

            // Existing flow kept unchanged for now.
            $user->address = $data['address'];
            $user->save();

            /*
            |--------------------------------------------------------------------------
            | Vendor assignment
            |--------------------------------------------------------------------------
            */

            if (($data['service_handle'] ?? null) === 'vendor') {
                if (!$user->hasRole('vendor')) {
                    $user->assignRole('vendor');
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Delivery boy assignment
            |--------------------------------------------------------------------------
            */

            if (($data['service_handle'] ?? null) === 'workman-delivery-boy') {
                if (!$user->hasRole('workman-delivery-boy')) {
                    $user->assignRole('workman-delivery-boy');
                }

                DB::table('delivery_tasks')->updateOrInsert(
                    [
                        'delivery_exec_id'     => $user->id,
                        'subscription_type_id' => $data['subscription_type_id'] ?? null,
                    ],
                    [
                        'delivery_task' => 'Delivery Assignment',
                        'zone_id'       => $data['zone_id'] ?? 1,
                        'status'        => 'today',
                        'start_date'    => now()->toDateString(),
                        'updated_at'    => now(),
                        'created_at'    => now(),
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Vendor / Workman default address
            |--------------------------------------------------------------------------
            */

            Address::updateOrCreate(
                [
                    'addressable_type' => User::class,
                    'addressable_id'   => $user->id,
                    'is_default'       => true,
                ],
                [
                    'line1' => $data['address'],
                    'lat'   => $data['latitude'] ?? null,
                    'lng'   => $data['longitude'] ?? null,
                ]
            );
        });

        return response()->json([
            'message' => 'Profile saved successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Get Logged-in User Addresses
    |--------------------------------------------------------------------------
    */

    public function getAddresses(Request $request)
    {
        $user = $request->user();

        $addresses = $user->addresses()
            ->with('zone')
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'addresses' => $addresses,
            'user' => [
                'name'  => $user->name,
                'phone' => $user->phone,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Save New Address
    |--------------------------------------------------------------------------
    */

    public function saveAddress(Request $request)
    {
        $data = $this->validateAddress($request);

        $user = $request->user();

        $address = DB::transaction(function () use ($user, $data) {
            $hasAddresses = $user->addresses()->exists();

            // First address automatically becomes default.
            $makeDefault = !$hasAddresses || ($data['is_default'] ?? false);

            if ($makeDefault) {
                $user->addresses()->update([
                    'is_default' => false,
                ]);
            }

            return $user->addresses()->create([
                'label'          => $data['label'] ?? 'Home',
                'receiver_name'  => $data['receiver_name'],
                'receiver_phone' => $data['receiver_phone'],
                'line1'          => $data['line1'],
                'line2'      => $data['line2'] ?? null,
                'nagar'      => $data['nagar'] ?? null,
                'city'       => $data['city'],
                'state'      => $data['state'] ?? null,
                'pincode'    => $data['pincode'] ?? null,
                'lat'        => $data['lat'] ?? null,
                'lng'        => $data['lng'] ?? null,
                'zone_id'    => $data['zone_id'] ?? null,
                'is_default' => $makeDefault,
            ]);
        });

        return response()->json([
            'message' => 'Address saved successfully.',
            'address' => $address->load('zone'),
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Update Address
    |--------------------------------------------------------------------------
    */

    public function updateAddress(Request $request, int $id)
    {
        $data = $this->validateAddress($request, true);

        $user = $request->user();

        $address = $user->addresses()
            ->whereKey($id)
            ->firstOrFail();

        DB::transaction(function () use ($user, $address, $data) {
            $makeDefault = ($data['is_default'] ?? false) === true;

            if ($makeDefault) {
                $user->addresses()
                    ->where('id', '!=', $address->id)
                    ->update([
                        'is_default' => false,
                    ]);
            }

            $address->update([
                'label'          => $data['label'] ?? $address->label,
                'receiver_name'  => $data['receiver_name'] ?? $address->receiver_name,
                'receiver_phone' => $data['receiver_phone'] ?? $address->receiver_phone,
                'line1'          => $data['line1'] ?? $address->line1,
                'line2'      => array_key_exists('line2', $data)
                    ? $data['line2']
                    : $address->line2,
                'nagar'      => array_key_exists('nagar', $data)
                    ? $data['nagar']
                    : $address->nagar,
                'city'       => $data['city'] ?? $address->city,
                'state'      => array_key_exists('state', $data)
                    ? $data['state']
                    : $address->state,
                'pincode'    => array_key_exists('pincode', $data)
                    ? $data['pincode']
                    : $address->pincode,
                'lat'        => array_key_exists('lat', $data)
                    ? $data['lat']
                    : $address->lat,
                'lng'        => array_key_exists('lng', $data)
                    ? $data['lng']
                    : $address->lng,
                'zone_id'    => array_key_exists('zone_id', $data)
                    ? $data['zone_id']
                    : $address->zone_id,
                'is_default' => $makeDefault
                    ? true
                    : $address->is_default,
            ]);
        });

        return response()->json([
            'message' => 'Address updated successfully.',
            'address' => $address->fresh()->load('zone'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Delete Address
    |--------------------------------------------------------------------------
    */

    public function deleteAddress(Request $request, int $id)
    {
        $user = $request->user();

        $address = $user->addresses()
            ->whereKey($id)
            ->firstOrFail();

        DB::transaction(function () use ($user, $address) {
            $wasDefault = $address->is_default;

            $address->delete();

            // If default address was deleted, promote another address.
            if ($wasDefault) {
                $nextAddress = $user->addresses()
                    ->orderByDesc('id')
                    ->first();

                if ($nextAddress) {
                    $nextAddress->update([
                        'is_default' => true,
                    ]);
                }
            }
        });

        return response()->json([
            'message' => 'Address deleted successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Make Address Default
    |--------------------------------------------------------------------------
    */

    public function makeDefaultAddress(Request $request, int $id)
    {
        $user = $request->user();

        $address = $user->addresses()
            ->whereKey($id)
            ->firstOrFail();

        DB::transaction(function () use ($user, $address) {
            $user->addresses()->update([
                'is_default' => false,
            ]);

            $address->update([
                'is_default' => true,
            ]);
        });

        return response()->json([
            'message' => 'Default address updated successfully.',
            'address' => $address->fresh()->load('zone'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Address Validation
    |--------------------------------------------------------------------------
    */

    private function validateAddress(
        Request $request,
        bool $isUpdate = false
    ): array {
        $requiredOrSometimes = $isUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'label' => [
                'nullable',
                'string',
                'max:50',
            ],

            'receiver_name' => [
                $requiredOrSometimes,
                'string',
                'max:150',
            ],

            'receiver_phone' => [
                $requiredOrSometimes,
                'string',
                'max:20',
            ],

            'line1' => [
                $requiredOrSometimes,
                'string',
                'max:255',
            ],

            'line2' => [
                'nullable',
                'string',
                'max:255',
            ],

            'nagar' => [
                'nullable',
                'string',
                'max:150',
            ],

            'city' => [
                $requiredOrSometimes,
                'string',
                'max:100',
            ],

            'state' => [
                'nullable',
                'string',
                'max:100',
            ],

            'pincode' => [
                'nullable',
                'string',
                'max:10',
            ],

            'lat' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'lng' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'zone_id' => [
                'nullable',
                'integer',
                Rule::exists('zones', 'id'),
            ],

            'is_default' => [
                'sometimes',
                'boolean',
            ],
        ]);
    }

    public function getProfile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'name'   => $user->name,
            'phone'  => $user->phone,
            'email'  => $user->email,
            'gender' => $user->gender,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'gender' => [
                'nullable',
                Rule::in([
                    'Male',
                    'Female',
                    'Other',
                ]),
            ],
        ]);

        $user->update([
            'name'   => $data['name'],
            'email'  => $data['email'] ?? null,
            'gender' => $data['gender'] ?? null,
        ]);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'profile' => [
                'name'   => $user->name,
                'phone'  => $user->phone,
                'email'  => $user->email,
                'gender' => $user->gender,
            ],
        ]);
    }
}
