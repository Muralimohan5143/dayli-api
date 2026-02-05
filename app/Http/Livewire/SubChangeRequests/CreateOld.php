<?php

/*namespace App\Http\Livewire\SubChangeRequests;

use Livewire\Component;
use App\Models\SubChangeRequest;
use App\Models\User;
use App\Models\Product;
use Illuminate\Validation\Rule;

class Create extends Component
{
    public $for_user_id;
    public $by_user_id;
    public $from_id;
    public $product_id;
    public $product_count;
    public $frequency_type = 'daily';
    public $custom_frequency_format;
    public $change_reason = 'self_service';
    public $start_date;
    public $end_date;
    public $status = 'pending';

    public $users;
    public $products;

    public function mount()
    {
        $this->start_date = now()->toDateString();
        $this->users = User::all();
        $this->products = Product::all();
    }

    public function submit()

    {

        logger('SubChangeRequest::submit called'); // log debug

        $this->validate([
            'for_user_id' => 'required|exists:users,id',
            'by_user_id' => 'required|exists:users,id',
            'from_id' => 'nullable|exists:users,id',
            'product_id' => 'required|exists:products,product_id',
            'product_count' => 'required|integer|min:1',
            'frequency_type' => [
                'required',
                Rule::in(['daily', 'alternate_days', 'weekdays', 'weekends', 'sat', 'sun', 'custom', 'on_demand'])
            ],
            'custom_frequency_format' => 'nullable|string',
            'change_reason' => 'required',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|string',
        ]);

        logger('Before create is called'); // log debug
        SubChangeRequest::create($this->only([
            'for_user_id',
            'by_user_id',
            'from_id',
            'product_id',
            'product_count',
            'frequency_type',
            'custom_frequency_format',
            'change_reason',
            'start_date',
            'end_date',
            'status'
        ]));
        logger('After create is called'); // log debug
        return $this->redirect(route('sub-change-requests.index'));

        //return redirect()->route('sub-change-requests.index')->with('success', 'Request created.');
    }

    public function render()
    {
        return view('livewire.sub-change-requests.create');
    }
}
