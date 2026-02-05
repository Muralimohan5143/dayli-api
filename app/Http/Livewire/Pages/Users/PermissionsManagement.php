<?php

namespace App\Http\Livewire\Pages\Users;

use Livewire\Component;
use App\Models\Permission;

class PermissionsManagement extends Component
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
        $type = Permission::find($id);
        $type->delete();
        $this->showSuccessNotification = true;
    }


    public function render()
    {
        return view('livewire.pages.users.permissions-management', [
            'permissions' => Permission::orderBy($this->sortField, $this->sortDirection)                       //::search($this->search)

                    ->get(),
        ]);
    }
}
