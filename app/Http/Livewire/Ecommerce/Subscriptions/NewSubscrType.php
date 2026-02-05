<?php

namespace App\Http\Livewire\Ecommerce\Subscriptions;

use App\Models\SubscrType;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class NewSubscrType extends Component
{
       use WithFileUploads;

        public $name = '';
        public $description = '';
        public $imgUrl = '';
        public $status = '';
        public $decommissionedDate;

        protected function rules() {
            return [
                'name' => 'required|unique:subscr_types',
                'description' => 'required',
                'imgUrl' => 'nullable|image|max:2000',
                'status' => 'in:PLANNING,ACTIVE',
                'decommissionedDate' => 'nullable',
            ];
        }

        protected $messages = [
            'name.required' => 'The name field is required',
            'description.required' => 'Please enter a valid description',
        ];

        public function mount() {

        }

        public function addSubscrType() {
            // if(is_array($this->category_id) && array_key_exists("value", $this->category_id)) {
            //     $this->category_id = intval($this->category_id['value']);
            // }
            $this->validate();
            $type = SubscrType::create([
                'name' => $this->name,
                'description' => $this->description,
                'img_url' => $this->imgUrl,
                'status' => $this->status,
                'decommissioned_date' => $this->decommissionedDate,
            ]);


            // $this->date && $type->update([
            //     'date' => Carbon::parse($this->date)->format('Y-m-d')
            // ]);

            // $this->imgUrl && $type->update([
            //     'img_url' => $this->imgUrl->store('/', 'items')
            // ]);
            // sort($this->tags_id);
            // $item->tags()->sync($this->tags_id, false);

            session()->flash('success', 'Your subscription type has been created.');
            return redirect(route('manage-subscr-types'));
        }

        public function render()
        {
            return view('livewire.ecommerce.subscriptions.new-subscr-type');
        }

}
