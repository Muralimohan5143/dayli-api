<?php

namespace App\Http\Livewire\Pages\Users;

use Livewire\Component;
use App\Models\UserAttrType;

class NewUserAttrType extends Component
{
    public $name = '';
    public $description = '';
    public $status = '';
    public $deactivatedDate;

    protected function rules() {
        return [
            'name' => 'required|unique:user_attr_types',
            'description' => 'required',
            'status' => 'in:INACTIVE,ACTIVE',
            'deactivatedDate' => 'nullable',
        ];
    }

    protected $messages = [
        'name.required' => 'The name field is required',
        'description.required' => 'Please enter a valid description',
    ];

    public function mount() {

    }

    public function addUserAttr() {
        $this->validate();
        $type = UserAttrType::create([
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'deactivated_date' => $this->deactivatedDate,
        ]);


        // $this->date && $type->update([
        //     'date' => Carbon::parse($this->date)->format('Y-m-d')
        // ]);

        // $this->imgUrl && $type->update([
        //     'img_url' => $this->imgUrl->store('/', 'items')
        // ]);
        // sort($this->tags_id);
        // $item->tags()->sync($this->tags_id, false);

        session()->flash('success', 'Your user attribute has been created.');
        return redirect(route('manage-user-attrs'));
    }

    public function render()
    {
        return view('livewire.pages.users.new-user-attr-type');
    }
}
