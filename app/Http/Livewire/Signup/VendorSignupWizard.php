<?php

namespace App\Http\Livewire\Signup;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\ZonePincode;
use App\Services\Geo\ReverseGeocoder;

class VendorSignupWizard extends Component
{
    /** 1 = Signin, 2 = Contract, 3 = Profile */
    public int $step = 1;
    public ?int $zoneId = null;
    public ?int $vendorId = null;
    public ?string $pincode = null;
    public ?float $lat = null;
    public ?float $lon = null;


    
    public ?string $type = null;                 // keep legacy "type" but don't prefill on Step 2

    public ?string $role = null;
    //public ?string $subscriptionType = null;
    public array $subtypes = [];
    public string $subscriptionType = '';


    /** user_id captured after OTP verify in Step 1 */
    public ?int $pendingUserId = null;

    protected $listeners = [
        'signin:created'     => 'onSigninCreated',     // from Step 1 after OTP success
        'moveToStep'         => 'onMoveToStep',        // from children/footer
        'otpVerified'        => 'onOtpVerified',       // optional legacy event
        'contractSaved'      => 'onContractSaved',     // from Step 2
        'contract:completed' => 'onContractCompleted', // alternative from Step 2
    ];



    #[On('zoneSelected')]
    public function setZone($zoneId = null, $pincode = null, $lat = null, $lon = null)
    {
        if ($zoneId) {
            $this->zoneId = (int) $zoneId;
            return;
        }

        if ($pincode) {
            $this->pincode = $pincode;
            $this->zoneId = optional(ZonePincode::where('pin_code', $pincode)->first())->zone_id;
            return;
        }

        if ($lat && $lon) {
            $this->lat = (float) $lat;
            $this->lon = (float) $lon;
            // Reverse geocode to pincode → zone
            $rev = app(ReverseGeocoder::class);
            $pin = $rev->pincodeFromLatLon($this->lat, $this->lon);
            if ($pin) {
                $this->pincode = $pin;
                $this->zoneId = optional(ZonePincode::where('pin_code', $pin)->first())->zone_id;
            }
        }
    }
    public function mount(): void
    {
        $this->step = (int) request('step', 1);
        $this->step = (int) request('step', $this->step);

        // Legacy support: allow ?type=... except while landing on Contract (Step 2)
        $this->type = (string) request('type', $this->type);
        // Example subtypes — replace with DB query if needed
        $this->subtypes = [
            ['key' => 'milk',   'label' => 'Milk'],
            ['key' => 'curd',   'label' => 'Curd'],
            ['key' => 'paneer', 'label' => 'Paneer'],
            ['key' => 'cheese', 'label' => 'Cheese'],
        ];

        $this->subscriptionType = $this->type . '_dairy';
        //$this->vendorId = auth()->id(); // or however you identify vendor

        // NEW: Contract page should always start clean (no preselected type / role)
        if ($this->step === 2) {
            $this->resetContractState();
        }
    }

    public function onContractCompleted(array $payload): void
    {
        $this->role = $payload['role'] ?? null;
        $this->subscriptionType = $payload['subscriptionType'] ?? null;
        $this->moveToStep(3);
    }

    public function moveToStep(int $step): void
    {
        $this->step = $step;
    }

    /** Step 1 → receive user id (no type-hint to avoid Redirector->int errors) */
    public function onSigninCreated($userId): void
    {
        if (
            $userId instanceof \Livewire\Features\SupportRedirects\Redirector
            || $userId instanceof \Illuminate\Http\RedirectResponse
        ) {
            return;
        }
        if (is_array($userId) && isset($userId['id'])) {
            $userId = $userId['id'];
        }
        if (!is_numeric($userId)) {
            logger('signin:created payload not numeric', ['received' => $userId]);
            return;
        }

        $this->pendingUserId = (int) $userId;
        session(['signup_user_id' => $this->pendingUserId]);

        // NEW: ensure a clean Contract step
        $this->resetContractState();
        $this->step = 2;
    }

    /** Children can move the step (untyped + guard) */
    #[On('moveToStep')]
    public function onMoveToStep($step): void
    {
        if (
            $step instanceof \Livewire\Features\SupportRedirects\Redirector
            || $step instanceof \Illuminate\Http\RedirectResponse
        ) {
            return;
        }
        if (is_array($step) && isset($step['step'])) {
            $step = $step['step'];
        }
        if (!is_numeric($step)) {
            logger('moveToStep payload not numeric', ['received' => $step]);
            return;
        }

        $step = max(1, min(3, (int) $step));

        // NEW: whenever navigating to Step 2, clear role/type so UI hides sub-types
        if ($step === 2) {
            $this->resetContractState();
        }

        $this->step = $step;
    }

    /** Optional legacy event from Signin */
    #[On('otpVerified')]
    public function onOtpVerified(): void
    {
        // NEW: go to a clean Contract step
        $this->resetContractState();
        $this->onMoveToStep(2);
    }

    /** Step 2 saved → go to Step 3 */
    #[On('contractSaved')]
    public function onContractSaved(array $payload = []): void
    {
        // (optional) stash payload if you need it later
        $this->onMoveToStep(3);
    }

    public function render()
    {
        return view('livewire.signup.vendor-signup-wizard', [
            'subtypes'         => $this->subtypes,
            'subscriptionType' => $this->subscriptionType,
            'zoneId'           => $this->zoneId,
            'vendorId'         => $this->vendorId,
        ]);
    }

    /* ------------------------- *
     |      Private helpers      |
     * ------------------------- */

    /** NEW: Clear all Step-2 selections so Contract page starts blank */
    private function resetContractState(): void
    {
        $this->role = null;
        //  $this->subscriptionType = null;
        // IMPORTANT: do not pre-seed "type" while on Step 2
        $this->type = null;
    }
}
