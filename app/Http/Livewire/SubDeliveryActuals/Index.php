<?php

namespace App\Http\Livewire\SubDeliveryActuals;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\SubDeliveryActual;

class Index extends Component
{
    use WithPagination;

    public $search = '';
    public $perPage = 10;

    protected $queryString = ['search'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $deliveries = SubDeliveryActual::with(['customer', 'product'])
            ->whereHas('customer', fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orWhere('status', 'like', "%{$this->search}%")
            ->latest()
            ->paginate($this->perPage);

        return view('livewire.sub-delivery-actuals.index', [
            'deliveries' => $deliveries,
        ]);
    }
}
