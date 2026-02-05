<?php

namespace App\Http\Livewire\Leads;

use Livewire\Component;
use App\Models\Lead;

class EditLead extends Component
{
    public $lead;

    public $first_name, $last_name, $phone, $alternate_phone, $email, $lang_locale;
    public $address1, $address2, $city, $state, $pincode;
    public $lead_type, $zone, $source, $notes;
    public $status, $follow_up_date;

    public function mount(Lead $lead)
    {
        $this->lead = $lead;

        $this->fill($lead->toArray());
    }

    protected $rules = [
        'first_name' => 'required|string|max:255',
        'phone' => 'required|string|max:15',
    ];

    public function update()
    {
        $this->validate();

        $this->lead->update([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'alternate_phone' => $this->alternate_phone,
            'email' => $this->email,
            'lang_locale' => $this->lang_locale,
            'address1' => $this->address1,
            'address2' => $this->address2,
            'city' => $this->city,
            'state' => $this->state,
            'pincode' => $this->pincode,
            'lead_type' => $this->lead_type,
            'zone' => $this->zone,
            'source' => $this->source,
            'notes' => $this->notes,
            'status' => $this->status,
            'follow_up_date' => $this->follow_up_date,
        ]);

        session()->flash('success', 'Lead updated successfully!');
        return redirect()->route('leads.index');
    }

    public function render()
    {
        return view('livewire.leads.edit-lead');
    }
}
