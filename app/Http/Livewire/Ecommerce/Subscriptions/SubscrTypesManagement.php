<?php

namespace App\Http\Livewire\Ecommerce\Subscriptions;

use Livewire\Component;
use App\Models\SubscrType as SubscrType;

class SubscrTypesManagement extends Component
{
    public function render()
    {
        return view('livewire.ecommerce.subscriptions.subscr-types-management', [
            'types' => SubscrType::orderBy($this->sortField, $this->sortDirection)->get()
                //->paginate($this->perPage)
        ]);
    }
    public $showSuccessNotification = false;
    public $showFailureNotification = false;

    public $search = '';
    public $sortField = 'id';
    public $sortDirection = 'asc';
    public $perPage = 10;

    protected $queryString = ['sortField', 'sortDirection'];
    protected $paginationTheme = 'bootstrap';

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }
        $this->sortField = $field;
    }

    public function delete($id)
    {
        $type = SubscrType::find($id);
        $type->delete();
        $this->showSuccessNotification = true;
    }
}
