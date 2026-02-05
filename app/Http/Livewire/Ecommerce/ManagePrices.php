<?php

namespace App\Http\Livewire\Ecommerce;

use App\Models\Product;
use App\Models\Variant;
use Livewire\Component;
use Signifly\Shopify\Shopify;
use Illuminate\Support\Facades\DB;

class ManagePrices extends Component
{

    protected $product_types =
    [
        'Vegetable' => ['name' => 'Veggies', 'data' => []],
        'Leafy Veg' => ['name' => 'Leafy-Veg', 'data' => []],
        'Fruit' => ['name' => 'Fruits', 'data' => []],
    ];

    protected $shopify;

    public function boot(Shopify $shopify)
    {
        $this->shopify = $shopify;
    }

    public function syncProducts()
    {
        // Truncate the tables first.
        DB::table('products')->truncate();
        DB::table('variants')->truncate();

        foreach (array_keys($this->product_types) as $key) {
            $products = $this->shopify->getProducts(['product_type' => $key]);

            foreach ($products as $product) {
                $newProduct = new Product;

                $newProduct->product_id = $product->id;
                $newProduct->title = $product->title;
                $newProduct->vendor = $product->vendor;
                $newProduct->product_type = $product->product_type;
                $newProduct->handle = $product->handle;
                $newProduct->tags = $product->tags;
                $newProduct->status = $product->status;
                $newProduct->img_src = $product->image['src'];

                $newProduct->save();

                $variants = $product->variants;
                $variantList = array();

                foreach ($variants as $variant) {
                    $newVariant = new Variant;
                    $newVariant->variant_id = $variant['id'];
                    $newVariant->product_id = $variant['product_id'];
                    $newVariant->title = $variant['title'];

                    $newVariant->title = $variant['title'];
                    $newVariant->price = $variant['price'];
                    $newVariant->compare_at_price = $variant['compare_at_price'] ?? '';

                    $variantImgId = $variant['image_id'];
                    $variantImgSrc = "";

                    foreach ($product->images as $image) {
                        if ($image['id'] == $variantImgId) {
                            $variantImgSrc = $image['src'];
                        }
                    }
                    $newVariant->img_src = $variantImgSrc;
                    $newVariant->paf = 1;
                    array_push($variantList, $newVariant);
                }
                $newProduct->variants()->saveMany($variantList);
            }
        }
        return $products;
    }

    public function render()
    {
        $products = Product::whereIn('product_type', array_keys($this->product_types))->get();
        foreach ($products as $product) {
            array_push($this->product_types[$product->product_type]['data'], $product);
        };
        return view('livewire.ecommerce.manage-prices')->with('product_types', $this->product_types);

        //        return view('livewire.ecommerce.products.products-list');
    }
}
