<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Zone;   // ✅ ADD
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Services\InteraktService;

class OtpAuthController extends Controller
{
    /**
     * POST /api/auth/send-otp
     * body: { "phone": "9898136781" }   // or "+919898136781"
     */

    public function sendOtp(Request $request, InteraktService $interakt)
    {
        $v = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'min:8', 'max:20'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors'  => $v->errors(),
            ], 422);
        }

        $rawPhone = $request->input('phone');      // what user typed
        $digits   = preg_replace('/\D+/', '', $rawPhone);   // only numbers

        // build candidate formats to match your schema
        $last10     = substr($digits, -10);        // last 10 digits
        $plusDigits = '+' . $digits;               // +919898136781 OR +9898136781
        $plus10     = '+' . $last10;               // +9898136781
        $plus91     = '+91' . $last10;             // +919898136781

        // 1️⃣ FIND CUSTOMER IN `users` TABLE
        $customer = User::query()
            ->where('phone', $rawPhone)
            ->orWhere('phone', $digits)
            ->orWhere('phone', $plusDigits)
            ->orWhere('phone', $plus10)
            ->orWhere('phone', $plus91)
            ->orWhere('phone_normalized', $plusDigits)
            ->orWhere('phone_normalized', $plus10)
            ->orWhere('phone_normalized', $plus91)
            ->first();


        // 🔥 IF NOT FOUND → CREATE NEW USER
        if (! $customer) {
            $customer = new User();
            $customer->display_name = 'Dayli User';
            $customer->phone        = $plus91;   // only set phone
            // ❌ REMOVE phone_normalized
            $customer->email        = null;
            $customer->password     = bcrypt(Str::random(32));
            $customer->save();
        }

        // 2️⃣ GENERATE OTP (SERVER-SIDE)
        $otp = (string) random_int(100000, 999999);

        // 3️⃣ STORE OTP IN user_otps (NO phone column, only user_id)
        DB::table('user_otps')->insert([
            'user_id'    => $customer->id,
            'otp'        => $otp,
            'expire_at'  => Carbon::now()->addMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        //if (!app()->environment(['local', 'testing'])) 
            {
            //4️⃣ SEND OTP VIA INTERAKT (WhatsApp)
            // $result = $interakt->sendOtp($last10, $otp);

            // if (! $result['ok']) {
            //     return response()->json([
            //         'message'  => 'Failed to send OTP on WhatsApp',
            //         'interakt' => $result,   // helpful for debug
            //     ], 500);
            // }
        }



        return response()->json([
            'message' => 'OTP sent',
            // dev/testing only – remove in production
            'otp' => app()->environment(['local', 'testing']) ? $otp : null,
        ]);
    }

    /**
     * POST /api/auth/verify-otp
     * body: { "phone": "9898136781", "otp": "123456" }
     */
    public function verifyOtp(Request $request)
    {
        $v = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'otp'   => ['required', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors'  => $v->errors(),
            ], 422);
        }

        $rawPhone = $request->input('phone');
        $inputOtp = $request->input('otp');

        $digits     = preg_replace('/\D+/', '', $rawPhone);
        $last10     = substr($digits, -10);
        $plusDigits = '+' . $digits;
        $plus10     = '+' . $last10;
        $plus91     = '+91' . $last10;

        // 1️⃣ FIND USER (same logic as sendOtp)
        $user = User::query()
            ->where('phone', $rawPhone)
            ->orWhere('phone', $digits)
            ->orWhere('phone', $plusDigits)
            ->orWhere('phone', $plus10)
            ->orWhere('phone', $plus91)
            ->orWhere('phone_normalized', $plusDigits)
            ->orWhere('phone_normalized', $plus91)
            ->first();

        if (! $user) {
            return response()->json([
                'message' => 'Phone not registered:laravel',
            ], 404);
        }

        // 2️⃣ GET LATEST OTP FOR THIS USER
        $otpRow = DB::table('user_otps')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();

        if (! $otpRow) {
            return response()->json([
                'message' => 'OTP not found, please request again',
            ], 400);
        }

        if (Carbon::parse($otpRow->expire_at)->isPast()) {
            return response()->json([
                'message' => 'OTP expired',
            ], 400);
        }

        if ((string) $otpRow->otp !== (string) $inputOtp) {
            return response()->json([
                'message' => 'Invalid OTP',
            ], 400);
        }

        // (optional) mark OTP used
        // DB::table('user_otps')->where('id', $otpRow->id)->delete();


        // ✅ Resolve & save zone_id from user's saved pincode
        if (is_null($user->zone_id)) {

            $pincode = $user->pincode ?? null;

            if (!empty($pincode)) {
                // normalize pincode
                $pincode = preg_replace('/\D+/', '', (string)$pincode);

                if (strlen($pincode) === 6) {
                    $zone = Zone::findByPinCode($pincode);

                    if ($zone && $zone->status === 'active') {
                        $user->zone_id = $zone->id;
                        $user->save();
                    }
                }
            }
        }



        // 3️⃣ ISSUE SANCTUM TOKEN FOR THIS USER
        $token = $user->createToken('dayli-mobile')->plainTextToken;


        return response()->json([
            'token'        => $token,
            'user_id'      => $user->id,
            'name'         => $user->display_name,
            'display_name' => $user->display_name,
            'phone'        => $user->phone,
            'pincode'      => $user->pincode,   // ✅ ADD
            'zone_id'      => $user->zone_id,   // ✅ ADD
        ]);
    }
}
