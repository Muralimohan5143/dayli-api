<?php

namespace App\Http\Livewire\Auth;

use Livewire\Component;

class LoginForm extends Component
{
    public $loginInput;
    public $otp = ['', '', '', '', ''];
    public $otpSent = false;
    public $resendCountdown = 30;

    public function sendOtp()
    {
        $this->validate([
            'loginInput' => 'required',
        ]);

        // TODO: Trigger OTP send logic via SMS/email based on input

        $this->otpSent = true;
        $this->resendCountdown = 30;
        $this->startTimer();
    }

    public function startTimer()
    {
        $this->dispatchBrowserEvent('start-countdown');
    }

    public function verifyOtp()
    {
        // TODO: OTP verification logic
        // Combine $this->otp into string if needed
    }

    public function render()
    {
        return view('livewire.auth.login-form');
    }
}
