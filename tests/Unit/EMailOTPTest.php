<?php

namespace Tests\Unit;

use App\Mail\SendOtpMail;
use Tests\TestCase;
use Livewire\Livewire;
use Illuminate\Support\Facades\Mail;
use App\Http\Livewire\Auth\Signin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\SentMessage; // <-- add this if using Option B

class EmailOTPTest extends TestCase
{
    use RefreshDatabase;

   

    public function test_otp_email_sends_via_ses()
    {
        Mail::fake();

        $email = 'murali.mohan@omnea.in';

        Livewire::test(Signin::class)
            ->set('contact', $email)
            ->call('sendOtp');

        Mail::assertSent(SendOtpMail::class, function (SendOtpMail $mail) use ($email) {
            return $mail->hasTo($email);
        });
    }
}
