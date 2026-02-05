<?php

namespace App\View\Components;

use Illuminate\View\Component;

class StepBand extends Component
{
    public int $step;

    public function __construct(int $step = 1)
    {
        $this->step = $step;
    }

    public function render()
    {
        return view('components.step-band');
    }
}
