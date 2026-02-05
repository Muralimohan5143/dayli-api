<div>
    <div class="container">
        <div class="row justify-content-center my-2">
        <button class="btn btn-info" wire:click="syncProducts">{{ __('Sync Products') }}</button>
        </div>
        <div class="row justify-content-center my-2">
            <div class="col-sm-5">

                <ul class="nav nav-pills nav-fill mb-3" id="products-tab" role="tablist">
                    @foreach ($product_types as $key=>$product_type)
                    <li class="nav-item" role="presentation">
                        <button  class="nav-link {{ $loop->index == 0 ? 'active' : ''}}" id="{{ $product_type['name'] }}-tab" data-bs-toggle="pill" data-bs-target="#product_type-{{$product_type['name']}}" type="button" role="tab" aria-controls="products-{{$product_type['name']}}" aria-selected="true">{{$key}}</button>
                    </li>
                    @endforeach
                </ul>
                <div class="tab-content" id="products-tabContent">

                    @foreach ($product_types as $key=>$product_type)
                    <div class="tab-pane fade {{$loop->index == 0 ? 'show active ' : '' }}" id="product_type-{{$product_type['name']}}" role="tabpanel" aria-labelledby="product_type-{{$product_type['name']}}-tab">

                        @foreach ($product_type['data'] as $key=>$product)
                        @livewire('ecommerce.products.product', ['product_id' => $product->product_id])
                        @endforeach

                        <!-- <div class="tab-pane active" id="pills-home" role="tabpanel" aria-labelledby="pills-home-tab">tab1</div>
                            <div class="tab-pane " id="pills-profile" role="tabpanel" aria-labelledby="pills-profile-tab">tab2</div>
                            <div class="tab-pane " id="pills-contact" role="tabpanel" aria-labelledby="pills-contact-tab">tab3</div> -->
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>
</div>
