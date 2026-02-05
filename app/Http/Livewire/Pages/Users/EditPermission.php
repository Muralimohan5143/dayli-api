<?php

namespace App\Http\Livewire\Pages\Users;

use Livewire\Component;
use Illuminate\Validation\Rule;
use App\Models\Permission;

class EditPermission extends Component
{
    public $permission;
    public $name = '';
    public $description = '';
    public $status = '';
    public $deactivatedDate = '';

    protected function rules()
    {
        return [
            'name' => ['required', Rule::unique('permissions', 'name')->ignore($this->permission)],
            'description' => 'required',
            'status' => 'in:INACTIVE,ACTIVE',
            'deactivatedDate' => 'nullable',
        ];
    }

    public function mount($id)
    {
        if ((auth()->user()->isAdmin() || auth()->user()->isCreator()) && Permission::find($id) !== null) {
            $this->permission = Permission::find($id);

            $this->name = $this->permission->name;
            $this->description = $this->permission->description;
            $this->status = $this->permission->status;
            $this->deactivatedDate = $this->permission->deactivated_date;
        } else {
            redirect('404');
        }
    }

    public function editPermission()
    {
        $this->validate();
        $this->permission->name = $this->name;
        $this->permission->description = $this->description;
        $this->permission->status = $this->status;
        $this->permission->deactivated_date = strtolower($this->status) === 'inactive' ? date('Y-m-d') : null;
        $this->permission->update();

        session()->flash('success', 'Your permission has been edited.');
        return redirect(route('manage-permissions'));
    }

    public function render()
    {
        return view('livewire.pages.users.edit-permission');
    }
}
