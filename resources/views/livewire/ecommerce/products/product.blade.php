<div class="row justify-content-center border-bottom border-info my-2">
    <div class="col text-center border-start border-end border-primary ">

        <div >
            <div>
                <img src="{{ $img_src }}" class="rounded-circle img-fluid" width="100px" height="100px"/>
            </div>
            <div class="text-center ">
                <span class="m-2 text-center text-green-700">{{$title}}</span>
            </div>
        </div>
        @foreach ($product->variants as $key=>$variant)
        <div>
            <span class="mb-1 bg-warning text-xs text-black">{{$variant->title}}</span>
        </div>

        <div>
            <!-- <label for="price"> <span class="m-1 rounded bg-warning text-black">{{$variant->title}}</span></label> -->
            <input id="price" type="text" class="w-15 mb-1 text-xs" wire:dirty.class="border border-warning" wire:model.blur="variants.{{$key}}">
        </div>
        <div wire:dirty wire:target="variants.{{$key}}">
            <span class="tiny text-lime-400">Updating...</span>
        </div>
        <div>
            @error('price') <span class="error">{{ $message }}</span> @enderror
        </div>
        @endforeach
    </div>
</div>
