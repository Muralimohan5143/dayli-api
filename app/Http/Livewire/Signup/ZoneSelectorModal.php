<?php

namespace App\Http\Livewire\Signup;

use App\Models\ZonePincode;
use Livewire\Component;

class ZoneSelectorModal extends Component
{
    public $show = true;
    public $pincode = '';
    public $lat;
    public $lon;

    protected $rules = [
        'pincode' => 'nullable|digits:6',
    ];

    public function selectByPincode()
    {
            $this->validateOnly('pincode');

        $zp = ZonePincode::where('pin_code', $this->pincode)->first();
        if (! $zp) {
            $this->addError('pincode', 'No zone found for this pincode');
            return;
        }

        $this->dispatch('zoneSelected', zoneId: $zp->zone_id, pincode: $this->pincode);
        $this->show = false;
    }

    public function selectByLocation($lat, $lon)
    {
        $this->lat = (float) $lat;
        $this->lon = (float) $lon;

        $rev = app(\App\Services\Geo\ReverseGeocoder::class);
        $pin = $rev->pincodeFromLatLon($this->lat, $this->lon);

        if ($pin) {
            $zp = \App\Models\ZonePincode::where('pin_code', $pin)->first();
            if ($zp) {
                $this->dispatch('zoneSelected', zoneId: $zp->zone_id, pincode: $pin, lat: $this->lat, lon: $this->lon);
                $this->show = false;
                return;
            }
        }
        // If unresolved, fall back to parent
        $this->dispatch('zoneSelected', lat: $this->lat, lon: $this->lon);
        $this->show = false;
    }

    public function render()
    {
        return view('livewire.signup.zone-selector-modal');
    }
}
