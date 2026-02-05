            <div class="container">
                <div class="row justify-content-center my-2">
                    <div class="col-sm-5">

                        <ul class="nav nav-pills mb-3" id="products-tab" role="tablist">
                            @foreach ($product_types as $key=>$product_type)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{$loop->index == 0 ? 'active' : ''}}" id="{{$key}}-tab" data-bs-toggle="pill" data-bs-target="#product_type-{{$key}}" type="button" role="tab" aria-controls="products-{{$key}}" aria-selected="true">{{$product_type['name']}}</button>
                            </li>
                            @endforeach

                            <!-- <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="pills-home-tab" data-bs-toggle="pill" data-bs-target="#pills-home" type="button" role="tab" aria-controls="pills-home" aria-selected="true">Home</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="pills-profile-tab" data-bs-toggle="pill" data-bs-target="#pills-profile" type="button" role="tab" aria-controls="pills-profile" aria-selected="false">Profile</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="pills-contact-tab" data-bs-toggle="pill" data-bs-target="#pills-contact" type="button" role="tab" aria-controls="pills-contact" aria-selected="false">Contact</button>
                            </li> -->
                        </ul>
                        <div class="tab-content" id="products-tabContent">

                            @foreach ($product_types as $key=>$product_type)
                            <div class="tab-pane {{$loop->index == 0 ? 'active' : ''}}" id="product_type-{{$key}}" role="tabpanel" aria-labelledby="product_type-{{$key}}-tab">

                                Tab{{$loop->index}}
                                @foreach ($product_type['data'] as $key=>$product)
                                @livewire('product', ['product_id' => $product->product_id])
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
