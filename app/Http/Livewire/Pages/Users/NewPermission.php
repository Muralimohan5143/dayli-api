<?php

namespace App\Http\Livewire\Pages\Users;

use Livewire\Component;
use App\Models\Permission;

class NewPermission extends Component
{
    public $name = '';
    public $description = '';
    public $status = '';
    public $deactivatedDate;

    protected function rules() {
        return [
            'name' => 'required|unique:permissions',
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

    public function addPermission() {
        $this->validate();
        $type = Permission::create([
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'deactivated_date' => strtolower($this->status) === 'inactive' ? date('Y-m-d') : null,
        ]);


        // $this->date && $type->update([
        //     'date' => Carbon::parse($this->date)->format('Y-m-d')
        // ]);

        // $this->imgUrl && $type->update([
        //     'img_url' => $this->imgUrl->store('/', 'items')
        // ]);
        // sort($this->tags_id);
        // $item->tags()->sync($this->tags_id, false);

        session()->flash('success', 'Your permission has been created.');
        return redirect(route('manage-permissions'));
    }

    public function render()
    {
        return view('livewire.pages.users.new-permission');
    }
}
