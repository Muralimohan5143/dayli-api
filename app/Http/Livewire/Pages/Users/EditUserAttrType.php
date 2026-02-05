<?php

namespace App\Http\Livewire\Pages\Users;

use Livewire\Component;
use Illuminate\Validation\Rule;
use App\Models\UserAttrType;

class EditUserAttrType extends Component
{
    public $attr;
    public $name = '';
    public $description = '';
    public $status = '';
    public $deactivatedDate = '';

    protected function rules()
    {
        return [
            'name' => ['required', Rule::unique('user_attr_types', 'name')->ignore($this->attr)],
            'description' => 'required',
            'status' => 'in:INACTIVE,ACTIVE',
            'deactivatedDate' => 'nullable',
        ];
    }

    public function mount($id)
    {
        if ((auth()->user()->isAdmin() || auth()->user()->isCreator()) && UserAttrType::find($id) !== null) {
            $this->attr = UserAttrType::find($id);

            $this->name = $this->attr->name;
            $this->description = $this->attr->description;
            $this->status = $this->attr->status;
            $this->deactivatedDate = $this->attr->deactivated_date;
        } else {
            redirect('404');
        }
    }

    public function editAttr()
    {
        $this->validate();
        $this->attr->name = $this->name;
        $this->attr->description = $this->description;
        $this->attr->status = $this->status;
        $this->attr->deactivated_date = strtolower($this->status) === 'inactive' ? date('Y-m-d') : null;
        $this->attr->update();

        session()->flash('success', 'Your user attribute has been edited.');
        return redirect(route('manage-user-attrs'));
    }

    public function render()
    {
        return view('livewire.pages.users.edit-user-attr-type');
    }
}
