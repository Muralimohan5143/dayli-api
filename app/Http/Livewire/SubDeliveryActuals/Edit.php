<?php

namespace App\Http\Livewire\SubDeliveryActuals;

use Livewire\Component;
use App\Models\SubDeliveryActual;
use App\Models\User;
use App\Models\Product;

class Edit extends Component
{
    public $deliveryId;
    public $subDeliveryActual;
    public $users;
    public $products;

    public $for_user_id;
    public $by_user_id;
    public $from_id;
    public $product_id;
    public $product_count;
    public $status;

    public function mount($id)
    {
        $this->subDeliveryActual = SubDeliveryActual::findOrFail($id);
        $this->deliveryId = $id;
        $this->users = User::all();
        $this->products = Product::all();

        $this->fill($this->subDeliveryActual->only([
            'for_user_id', 'by_user_id', 'from_id', 'product_id',
            'product_count', 'status'
        ]));
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

        $this->subDeliveryActual->update($this->only([
            'for_user_id', 'by_user_id', 'from_id', 'product_id',
            'product_count', 'status'
        ]));

        return redirect()->route('sub-delivery-actuals.index')->with('success', 'Delivery updated.');
    }

    public function render()
    {
        return view('livewire.sub-delivery-actuals.edit');
    }
}
