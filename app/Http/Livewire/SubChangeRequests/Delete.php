<?php

namespace App\Http\Livewire\SubChangeRequests;

use Livewire\Component;
use App\Models\SubChangeRequest;

class Delete extends Component
{
    public $requestId;
    public $confirming = false;

    public function mount($id)
    {
        $this->requestId = $id;
    }

    public function confirmDelete()
    {
        $this->confirming = true;
    }

    public function delete()
    {
        SubChangeRequest::findOrFail($this->requestId)->delete();
        session()->flash('success', 'Request deleted.');
        return redirect()->route('sub-change-requests.index');
    }

    public function render()
    {
        return view('livewire.sub-change-requests.Delete');
    }
}
