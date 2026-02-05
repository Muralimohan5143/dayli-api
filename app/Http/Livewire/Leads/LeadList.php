<?php

namespace App\Http\Livewire\Leads;

use Livewire\Component;
use App\Models\Lead;
use Livewire\WithPagination;

class LeadList extends Component
{
    use WithPagination;

    public $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $leads = Lead::where('first_name', 'like', '%' . $this->search . '%')
            ->orWhere('phone', 'like', '%' . $this->search . '%')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('livewire.leads.lead-list', [
            'leads' => $leads,
        ]);
    }
}
