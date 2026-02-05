<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use App\Mail\SendOtpMail;

class OtpSenderService
{
    public static function send(string $contact, string $otp): void
    {
        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            self::sendEmailOtp($contact, $otp);
        } elseif (preg_match('/^\+91[0-9]{10}$/', $contact)) {
            self::sendWhatsappOtp($contact, $otp);
            self::sendSmsOtp($contact, $otp);
        } elseif (preg_match('/^[0-9]{10}$/', $contact)) {
            $formatted = '+91' . $contact;
            self::sendWhatsappOtp($formatted, $otp);
            self::sendSmsOtp($formatted, $otp);
        } else {
            Log::warning('Unsupported contact format for OTP: ' . $contact);
        }
    }

    // public static function sendEmailOtp(string $toEmail, string $otp): void
    // {
    //     try {
    //         Mail::raw("Your OTP is: {$otp}", function ($message) use ($toEmail) {
    //             $message->from('admin@dayli.in', 'Dayli OTP');
    //             $message->to($toEmail)->subject('Your OTP');
    //         });
    //     } catch (\Throwable $e) {
    //         Log::error('Failed to send OTP via Email: ' . $e->getMessage());
    //     }
    // }



    public static function sendEmailOtp(string $toEmail, string $otp): void
    {
        try {
            Mail::to($toEmail)->send(new SendOtpMail($otp));
        } catch (\Throwable $e) {
            Log::error('Failed to send OTP via Email: ' . $e->getMessage());
        }
    }


    public static function sendWhatsappOtp(string $phoneWithCountryCode, string $otp): void
    {
        try {
            $res = Http::withHeaders([
                'Authorization' => 'Bearer eXdZU3JDVks3ZmtyZ19tUjUwOVhnODhSWjA0N1F1c3BxZWFpMTJtcWpLZzo=',
                'Content-Type' => 'application/json',
            ])->post('https://app.interakt.ai/v1/public/message/', [
                'receiver' => $phoneWithCountryCode,
                'type' => 'Template',
                'template' => [
                    'name' => 'otp_message', // make sure this exists in Interakt
                    'languageCode' => 'en',
                    'bodyValues' => [$otp],
                ]
            ]);

            if (!$res->ok()) {
                Log::warning('WhatsApp OTP send failed: ' . $res->body());
            }
        } catch (\Throwable $e) {
            Log::error('Exception in sending WhatsApp OTP: ' . $e->getMessage());
        }
    }

    public static function sendSmsOtp(string $phoneWithCountryCode, string $otp): void
    {
        try {
            // Replace with your actual Exotel SID and token
            $sid = 'your_exotel_sid';
            $token = 'your_exotel_token';
            $from = 'your_exotel_sender_id'; // e.g., EXOTEL or 10-digit number
            $to = preg_replace('/^\+91/', '', $phoneWithCountryCode);

            $res = Http::asForm()->withBasicAuth($sid, $token)->post("https://api.exotel.com/v1/Accounts/{$sid}/Sms/send", [
                'From' => $from,
                'To' => $to,
                'Body' => "Your OTP is {$otp} - Dayli"
            ]);

            if (!$res->ok()) {
                Log::warning('SMS OTP send failed: ' . $res->body());
            }
        } catch (\Throwable $e) {
            Log::error('Exception in sending SMS OTP: ' . $e->getMessage());
        }
    }
}
