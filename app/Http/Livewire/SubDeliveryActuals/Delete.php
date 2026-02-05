<?php

namespace App\Http\Livewire\SubDeliveryActuals;

use Livewire\Component;
use App\Models\SubDeliveryActual;

class Delete extends Component
{
    public $deliveryId;
    public $confirming = false;

    public function mount($id)
    {
        $this->deliveryId = $id;
    }

    public function confirmDelete()
    {
        $this->confirming = true;
    }

    public function delete()
    {
        $record = SubDeliveryActual::findOrFail($this->deliveryId);
        $record->delete();

        session()->flash('success', 'Delivery deleted.');
        return redirect()->route('sub-delivery-actuals.index');
    }

    public function render()
    {
        return view('livewire.sub-delivery-actuals.delete');
    }
}
