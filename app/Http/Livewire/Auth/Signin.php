<?php

namespace App\Http\Livewire\Auth;

use Livewire\Component;
use App\Models\User;
use App\Models\UserOtp;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Services\OtpSenderService;

class Signin extends Component
{
    public bool $embedded = false;
    public ?string $redirectUrl = null;
    public $contact = '';
    public $otp = ['', '', '', '', ''];
    public $otpSent = false;
    public $countdown = 30;
    public $resendReady = false;
    public $message = '';
    public $error = '';
    public $gen_otp = '';
    public int $otpKey = 1;                     // to force re-render after resend


    /** Redirect targets (route names) */
    // FIX: use your real dashboard route name
    protected string $redirectRouteDashboard = 'overview';
    // We won't use a vendor-contract route; the wizard controls steps itself.

    public function rules()
    {
        return [
            'contact' => ['required', function ($attribute, $value, $fail) {
                $val = is_array($value) ? implode('', $value) : (string) $value;

                if (ctype_digit($val)) {
                    if (strlen($val) !== 10) {
                        $fail('Phone number must be 10 digits.');
                    }
                } elseif (!preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $val)) {
                    $fail('Invalid email address.');
                }
            }],
            'otp'    => ['nullable', 'array', 'size:5'],
            'otp.*'  => ['nullable', 'digits:1'],
        ];
    }

    /* ---------- helpers to resolve "contact" into real DB columns ----------- */
    protected function isEmail(string $v): bool
    {
        return (bool) filter_var($v, FILTER_VALIDATE_EMAIL);
    }

    protected function normalizePhone(string $v): string
    {
        return preg_replace('/\D+/', '', $v ?? '') ?? '';
    }

    /** Returns ['email'=>...] or ['phone'=>...] */
    protected function contactWhere(string $input): array
    {
        $input = trim($input);
        if ($this->isEmail($input)) {
            return ['email' => strtolower($input)];
        }
        return ['phone' => $this->normalizePhone($input)];
    }

    public function updatedContact($value)
    {
        if (is_array($value)) {
            $this->contact = implode('', $value);
        }
    }

    public function getIsVendorSignupProperty(): bool
    {
        // true when this component is rendered inside the /vendor-signup page
        return request()->routeIs('vendor.signup');
    }

    public function sendOtp()
    {
        $this->resetErrorBag();
        $this->resetValidation();
        $this->message     = '';
        $this->error       = '';
        $this->resendReady = false;

        // Make sure OTP boxes are empty in Livewire state
        // (expects: public array $otp = ['', '', '', '', ''];)
        $this->otp = array_fill(0, 5, '');

        // Bump key so Blade remounts the inputs and drops any stale DOM values
        // (expects: public int $otpKey = 1; and wrapper :key="$otpKey" in Blade)
        $this->otpKey++;
        //$this->reset(['otp', 'message', 'error', 'resendReady']);

        if (!is_string($this->contact)) {
            $this->addError('contact', 'Invalid input format.');
            return;
        }

        $this->validate();

        $otp = rand(10000, 99999);
        $this->gen_otp = $otp;

        $where = $this->contactWhere((string) $this->contact);

        // Create/find user
        $user = User::firstOrCreate(
            $where,
            [
                'name'     => 'New User',
                'password' => bcrypt(Str::random(12)),
            ]
        );

        UserOtp::create([
            'user_id'   => $user->id,
            'otp'       => $otp,
            'expire_at' => now()->addMinutes(1),
        ]);

        try {
            OtpSenderService::send($this->contact, $otp);
        } catch (\Throwable $e) {
            logger('OTP send failed: ' . $e->getMessage());
            $this->addError('contact', 'Failed to send OTP. Try again.');
            return;
        }

        $this->otpSent     = true;
        $this->message     = 'OTP Sent!';
        $this->error       = '';
        $this->resendReady = false;
        $this->countdown   = 60;


        $this->dispatch(
            'start-otp-countdown',
            expiresIn: $this->countdown,
            devCode: app()->environment(['local', 'development']) ? $otp : null
        );
    }

    // Do NOT auto-verify on typing
    public function updatedOtp() {}

    public function verifyOtp()
    {
        $inputOtp = implode('', $this->otp);

        if ($inputOtp === '' || strlen($inputOtp) !== 5) {
            $this->addError('otp', 'Please enter the 5-digit OTP.');
            $this->dispatch('otp-invalid');
            return null;
        }

        $where = $this->contactWhere((string) $this->contact);
        $user  = User::where($where)->first();

        if (!$user) {
            $this->error = 'No such user found.';
            $this->dispatch('otp-invalid'); // <— add
            return null;
        }

        $otpRow = UserOtp::where('user_id', $user->id)
            ->where('otp', $inputOtp)
            ->where('expire_at', '>=', now())
            ->latest()
            ->first();

        if (!$otpRow) {
            $this->addError('otp', 'Invalid or expired OTP.');
            $this->dispatch('otp-invalid'); // <— add
            return null;
        }


        // OTP verified — tell the wizard which user was created
        $this->dispatch('signin:created', $user->id)
            ->to(\App\Http\Livewire\Signup\VendorSignupWizard::class);


        if ($this->embedded === false) {
            Auth::login($user);

            if (!empty($this->redirectUrl)) {
                return redirect()->to($this->redirectUrl);
            }
            return redirect()->route($this->redirectRouteDashboard);
        }

        return;
    }

    public function mount()
    {
        if (Auth::check()) {
            // If already logged in and a redirectUrl was provided, honor it first.
            if (!empty($this->redirectUrl)) {
                return redirect()->to($this->redirectUrl);
            }

            if ($this->isVendorSignup) {
                // Already logged in and on the signup wizard → jump to step 2 of the wizard
                return redirect()->route('vendor.signup', [
                    'step' => 2,
                    'type' => request('type', 'milk'),
                ]);
            }

            // Already logged in and on /login → dashboard
            return redirect()->route($this->redirectRouteDashboard);
        }
    }

    public function render()
    {
        return view('livewire.auth.signin');
    }
}
