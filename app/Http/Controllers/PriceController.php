<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class PriceController extends Controller
{
    protected $product_types =
    [
        'Vegetable' => ['name' => 'Veggies', 'data' => []],
        'Fruit' => ['name' => 'Fruits', 'data' => []],
        'Non-Veg' => ['name' => 'Non-Veg', 'data' => []],
    ];

    public function showPriceList()
    {
        $products = Product::whereIn('product_type', array_keys($this->product_types))->get();
        foreach ($products as $product) {
            array_push($this->product_types[$product->product_type]['data'], $product);
        };
        return view('livewire.ecommerce.pricelist')->with('product_types', $this->product_types);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    // public function store(StoreProductRequest $request)
    // {
    //     //
    // }

    // /**
    //  * Display the specified resource.
    //  */
    // public function show(Product $product)
    // {
    //     //
    // }

    // /**
    //  * Show the form for editing the specified resource.
    //  */
    // public function edit(Product $product)
    // {
    //     //
    // }

    // /**
    //  * Update the specified resource in storage.
    //  */
    // public function update(UpdateProductRequest $request, Product $product)
    // {
    //     //
    // }

    // /**
    //  * Remove the specified resource from storage.
    //  */
    // public function destroy(Product $product)
    // {
    //     //
    // }

}
