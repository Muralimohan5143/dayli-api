<?php

namespace App\Http\Livewire\Pages\Users;

use Livewire\Component;
use App\Models\UserAttrType;

class UserAttrTypesManagement extends Component
{
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
        $type = UserAttrType::find($id);
        $type->delete();
        $this->showSuccessNotification = true;
    }

    public function render()
    {
        return view('livewire.pages.users.user-attr-types-management', [
            'attrs' => UserAttrType::orderBy($this->sortField, $this->sortDirection)->get()
                //->paginate($this->perPage)
        ]);
    }
}
