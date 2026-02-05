<?php

namespace App\Http\Livewire\SubDeliveryActuals;

use Livewire\Component;
use App\Models\SubDeliveryActual;
use App\Models\User;
use App\Models\Product;

class Create extends Component
{
    public $for_user_id;
    public $by_user_id;
    public $from_id;
    public $product_id;
    public $product_count;
    public $status = 'pending_approval';

    public $users;
    public $products;

    public function mount()
    {
        $this->users = User::all();
        $this->products = Product::all();
    }

    public function submit()
    {
        $this->validate([
            'for_user_id' => 'required|exists:users,id',
            'by_user_id' => 'required|exists:users,id',
            'from_id' => 'nullable|exists:users,id',
            'product_id' => 'required|exists:products,id',
            'product_count' => 'required|integer|min:1',
            'status' => 'required|in:pending_approval,approved,rejected',
        ]);

        SubDeliveryActual::create($this->only([
            'for_user_id',
            'by_user_id',
            'from_id',
            'product_id',
            'product_count',
            'status'
        ]));

        return redirect()->route('sub-delivery-actuals.index')->with('success', 'Delivery logged.');
    }

    public function render()
    {
        return view('livewire.sub-delivery-actuals.create');
    }
}
