<?php

namespace App\Http\Livewire\Ecommerce\Subscriptions;

use Livewire\Component;
use App\Models\SubscrType;
use Illuminate\Validation\Rule;
use Livewire\WithFileUploads;

class EditSubscrType extends Component
{
    public $type;
    public $name = '';
    public $description = '';
    public $imgUrl = '';
    public $status = '';
    public $decommissionedDate = '';

    use WithFileUploads;

    protected function rules()
    {
        return [
            'name' => ['required', Rule::unique('subscr_types', 'name')->ignore($this->type)],
            'description' => 'required',
            'imgUrl' => 'nullable|image|max:2000',
            'status' => 'in:PLANNING,ACTIVE',
            'decommissionedDate' => 'nullable',
        ];
    }

    public function mount($id)
    {
        if ((auth()->user()->isAdmin() || auth()->user()->isCreator()) && SubscrType::find($id) !== null) {
            $this->type = SubscrType::find($id);

            $this->name = $this->type->name;
            $this->description = $this->type->description;
            $this->imgUrl = $this->type->img_url;
            $this->status = $this->type->status;
            $this->decommissionedDate = $this->type->decommissioned_date;
        } else {
            redirect('404');
        }
    }

    public function editType()
    {
        $this->validate();
        $this->type->name = $this->name;
        $this->type->description = $this->description;
        $this->type->img_url = $this->imgUrl;
        $this->type->status = $this->status;
        $this->type->decommissioned_date = $this->decommissionedDate;
        $this->type->update();

        session()->flash('success', 'Your subscription type has been edited.');
        return redirect(route('manage-user-attrs'));
    }

    public function render()
    {
        return view('livewire.ecommerce.subscriptions.edit-subscr-type');
    }
}
