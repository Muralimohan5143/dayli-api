<?php

namespace App\Http\Livewire\Ecommerce\Products;
use App\Models\Product;
use Livewire\Component;

class ProductsList extends Component
{

    protected $product_types =
    [
        'Vegetable' => ['name' => 'Veggies', 'data' => []],
        'Fruit' => ['name' => 'Fruits', 'data' => []],
        'Non-Veg' => ['name' => 'Non-Veg', 'data' => []],
    ];

    public function render()
    {
        $products = Product::whereIn('product_type', array_keys($this->product_types))->get();
        foreach ($products as $product) {
            array_push($this->product_types[$product->product_type]['data'], $product);
        };
        return view('livewire.ecommerce.pricelist')->with('product_types', $this->product_types);

//        return view('livewire.ecommerce.products.products-list');
    }
}
