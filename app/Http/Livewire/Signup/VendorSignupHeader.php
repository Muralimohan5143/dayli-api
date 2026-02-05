<?php

namespace App\Http\Livewire\Signup;

use Livewire\Component;

class VendorSignupHeader extends Component
{
    /** Current step (1=Contact, 2=Contract, 3=Profile) */
    public int $step = 1;

    /** Step labels */
    public array $labels = ['Contact Details', 'Contract Details', 'Profile Details'];

    /** Background hero image URL */
    public string $signupBg = '';

    /** Optional: listen for external step changes (from parent/wizard) */
    protected $listeners = [
        'setStep' => 'setStep',
        'nextStep' => 'nextStep',
        'prevStep' => 'prevStep',
    ];

    public function mount(): void
    {
        $total = count($this->labels);
        $requested = (int) request('step', $this->step);
        $this->step = max(1, min($requested, $total));

        $this->signupBg = $this->signupBg ?: asset('assets/img/bg/vernazza.jpg');
    }

    public function setStep(int $step): void
    {
        $this->step = max(1, min($step, count($this->labels)));
    }

    public function nextStep(): void
    {
        $this->setStep($this->step + 1);
    }

    public function prevStep(): void
    {
        $this->setStep($this->step - 1);
    }

    public function render()
    {
        $total   = count($this->labels);
        $current = max(1, min($this->step, $total));

        // 0% on step 1, 50% on step 2, 100% on step 3 (for 3 steps)
        $progressPct = $total > 1 ? (($current - 1) / ($total - 1)) * 100 : 0;
        $progressPct = max(0, min(100, $progressPct));

        return view('livewire.signup.vendor-signup-header', [
            'signupBg'    => $this->signupBg,
            'labels'      => $this->labels,
            'total'       => $total,
            'current'     => $current,
            'progressPct' => $progressPct,
        ]);
    }
}
