<?php

namespace App\Http\Livewire\Ecommerce\Products;

use App\Models\Product as ProductModel;
use Livewire\Component;
use Signifly\Shopify\Shopify;
//use Signifly\Shopify\Factory;

use function PHPUnit\Framework\isNull;

class Product extends Component
{
    //public VariantForm $form;

    public ?ProductModel $product;
    public $title;
    public $product_type;
    public $img_src;
    public $variants = array();
    protected $shopify;

    public function mount($product_id)
    {
        $product = ProductModel::find($product_id);
        if (!is_null($product)) {
            $this->title = $product->title;
            $this->product_type = $product->product_type;
            $this->img_src = $product->img_src;
            $this->product = $product;

            foreach ($product->variants as $key => $variant) {
                $this->variants[$key] = $variant->price;
            }
        }
        //$this->shopify = $shopify;
    }

    public function boot(Shopify $shopify) {
        $this->shopify = $shopify;
    }

    public function updated($property)
    {
        // $property: The name of the current property that was updated

        if (str_contains($property, 'variants')) {
            $updated_variant = explode('.',  $property);

            foreach ($this->product->variants as $key => $variant) {
                if ($updated_variant[1] == $key) {
                    $variant->price = $this->variants[$updated_variant[1]];
                    $variant->save();
                    $this->shopify->updateVariant($variant->variant_id, ['price' => $variant->price]);
                    break;
                }
            }
        }
    }
    public function save($variant_id, $price)
    {
        foreach ($this->product->variants as $variant) {
            if ($variant->id == $variant_id) {
                $variant->price = $price;
                $variant->save();
                break;
            }
        }
    }
}
