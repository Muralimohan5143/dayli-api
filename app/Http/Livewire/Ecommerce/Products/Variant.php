<?php

namespace App\Http\Livewire\Ecommerce\Products;

use App\Livewire\Forms\VariantForm;
use App\Models\Variant as VariantModel;
use Livewire\Component;
use Livewire\Attributes\Validate;

class Variant extends Component
{
     //public VariantForm $form;

     public ?VariantModel $variant;
     public $title;

     #[Validate('required|min:1')]
     public $price;

     #[Validate('required|min:1')]
     public $compare_at_price;
    //  #[Validate('required|min:1')]
    //  public $paf;

     public function mount($variant_id)
     {
         //$this->form->setVariant($variant);
         $variant = VariantModel::find($variant_id);
         $this->title = $variant->title;
         $this->price = $variant->price;
         //$this->compare_at_price = $variant->compare_at_price;
         //$this->paf = $variant->paf;
         $this->variant = $variant;
     }

    //  public function mount(VariantModel $variant)
    //  {
    //      //$this->form->setVariant($variant);
    //      $this->title = $variant->title;
    //      $this->price = $variant->price;
    //      $this->compare_at_price = $variant->compare_at_price;
    //      $this->paf = $variant->paf;
    //  }

    public function save()
    {
        //$this->variant->title = $this->title;
        $this->variant->price = $this->price;
        //$this->variant->compare_at_price = $this->compare_at_price;
        $this->variant->save();
    }
}
