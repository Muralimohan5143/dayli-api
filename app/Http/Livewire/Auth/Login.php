<?php

namespace App\Http\Livewire\Auth;

use Livewire\Component;

use App\Models\User;

class Login extends Component
{
    public $email = '';
    public $password = '';
    public $remember_me = false;

    public $contact = '';
    public $otp = ['', '', '', '', ''];
    public $otpSent = false;
    public $countdown = 30;
    public $timer;



    public function sendOtp()
    {
        
        $this->validate();
        $this->otpSent = true;
        $this->startCountdown();
        // Logic to send OTP goes here
    }

    public function startCountdown()
    {
        $this->countdown = 30;
    }


    protected $rules = [
        //'email' => 'required|email:rfc,dns',
        'contact' => 'required|min:6',
        'email' => 'required',
        'password' => 'required'
    ];

    public function mount() {
        if(auth()->user()) {
            return redirect()->intended('/ecommerce-manage-prices');
        }
        //$this->fill(['email' => 'admin@softui.com', 'password' => 'secret']);
    }

    public function login() {
        $credentials = $this->validate();

        if(auth()->attempt(['email' => $this->email, 'password' => $this->password], $this->remember_me)) {
            $user = User::where(["email" => $this->email])->first();
            auth()->login($user, $this->remember_me);
            return redirect()->intended('/ecommerce-manage-prices');
        }
        else{
            return $this->addError('email', trans('auth.failed'));
        }
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
