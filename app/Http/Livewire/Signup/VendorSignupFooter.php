<?php

namespace App\Http\Livewire\Signup;

use Livewire\Component;
use App\Http\Livewire\Signup\VendorSignupWizard;
use App\Http\Livewire\Signup\VendorContractDetails;

class VendorSignupFooter extends Component
{
    public int $step = 1;
    public ?string $type = null;



    public function mount(int $step = 1, ?string $type = null): void
    {
        $this->step = $step;
        $this->type = $type;
    }

    public function goPrev(): void
    {
        if ($this->step > 1) {
            $this->dispatch('moveToStep', $this->step - 1)
                ->to(VendorSignupWizard::class);
        }
    }

    public function goToNextStep(): void
    {
        if ($this->step >= 3) return;

        if ($this->step === 2) {
            
            $this->dispatch('contract:save')->to(VendorContractDetails::class);
            return;
        }

        $this->dispatch('moveToStep', $this->step + 1)
            ->to(VendorSignupWizard::class);
    }
    public function render()
    {
        return view('livewire.signup.vendor-signup-footer');
    }
}
