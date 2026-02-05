
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-9 mx-auto">
            <div class="card card-body mt-4">
                <h6 class="mb-0">{{ __('New Subscription Type') }}</h6>
                <p class="text-sm mb-0">{{ __('Create new subscription type') }}</p>
                <hr class="horizontal dark my-3">
                <form wire:submit="addSubscrType" action="#" method="POST">
                    <div class="avatar avatar-xxl position-relative">
                        <div class="@error('photo') has-danger @enderror">
                            <label for="file-input"
                                class="btn btn-sm btn-icon-only bg-gradient-light position-absolute bottom-0 end-0 mb-n2 me-n2">
                                <i class="fa fa-pen top-0" data-bs-toggle="tooltip" data-bs-placement="top" title=""
                                    aria-hidden="true" data-bs-original-title="Edit Image"
                                    aria-label="Edit Image"></i><span class="sr-only">{{ __('Edit Image') }}</span>
                            </label>

                            <input type="file" accept="image/*" wire:model.live="photo" id="file-input" class="d-none">
                            <span
                                class="h-12 w-12 rounded-full overflow-hidden bg-gray-100 @error('photo') is-invalid @enderror">
                                @if ($imgUrl)
                                    <img src="{{ $photo->temporaryUrl() }}" alt="Profile Photo">
                                @else
                                    <img src="/assets/img/image_placeholder.jpg" alt="Profile Photo">
                                @endif
                            </span>
                        </div>
                    </div>
                    <div>
                        @error('imgUrl') <div class="text-danger text-xs mt-3">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="itemName" class="form-label">{{ __('Subscription Type Name') }}</label>
                        <div class="@error('name') has-danger @enderror">
                            <input wire:model.blur="name" type="text"
                                class="form-control @error('name') is-invalid @enderror" id="projectName">
                        </div>
                        @error('name') <div class="text-danger text-xs">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="mt-4">{{ __('Description') }}</label>
                        <p class="form-text text-muted text-xs ms-1">
                            {{ __('This is how others will learn about the item, so make it good!') }}
                        </p>

                        <div wire:ignore>
                            <div x-data x-ref="quill" x-init="
                            quill = new Quill($refs.quill, {theme: 'snow'});
                            quill.on('text-change', function () {
                                $dispatch('quill-text-change', quill.root.innerHTML);
                            });
                            " x-on:quill-text-change.debounce.2000ms="@this.set('description', $event.detail)">
                                {!! $description !!}
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="mt-4 form-label">{{ __('Status') }}</label>
                        <div class="@error('status') has-danger @enderror d-flex flex-column">
                            <div  class="form-check">
                                <input wire:model.live="status" type="radio" class="form-check-input" name="status" id="active" value="ACTIVE">
                                <label class="custom-control-label" for="published">{{ __('Active') }}</label>
                            </div>
                            <div  class="form-check">
                                <input wire:model.live="status" type="radio" class="form-check-input" name="status" id="planning" value="PLANNING">
                                <label class="custom-control-label" for="draft">{{ __('Planning') }}</label>
                            </div>
                        </div>
                    </div>

                    <!-- <div>
                        <label class="mt-4 form-label">{{ __('Item Options') }}</label>
                        <div>
                            <div class="form-check">
                                <input wire:model.live="options.{{ 0 }}" class="form-check-input"
                                    name="options[]" id="optionFirst" type="checkbox" value="0">
                                <label class="custom-control-label" for="optionFirst">{{ __('First') }}</label>
                            </div>
                            <div class="form-check">
                                <input wire:model.live="options.{{ 1 }}" class="form-check-input"
                                    name="options[]" id="optionSecond" type="checkbox" value="1">
                                <label class="custom-control-label" for="optionSecond">{{ __('Second') }}</label>
                            </div>
                            <div class="form-check">
                                <input wire:model.live="options.{{ 2 }}" class="form-check-input"
                                    name="options[]" id="optionThird" type="checkbox" value="2">
                                <label class="custom-control-label" for="optionThird">{{ __('Third') }}</label>
                            </div>
                        </div>
                    </div> -->

                    <div>
                        <label for="date">{{ __('Date') }}</label>
                        <div>
                            <input wire:model.live="decommissionedDate" class="form-control datetimepicker" type="text"
                                placeholder="Please select date" data-input>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <a href="{{ route('manage-subscr-types') }}" type="button" name="button"
                            class="btn btn-light m-0">{{ __('Back to List') }}</a>
                        <button type="submit" name="button"
                            class="btn bg-gradient-primary m-0 ms-2">{{ __('Create User Attribute') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="../../../assets/js/plugins/choices.min.js"></script>
<script src="../../../assets/js/plugins/quill.min.js"></script>
<script src="../../../assets/js/plugins/flatpickr.min.js"></script>
<script src="../../../assets/js/plugins/dropzone.min.js"></script>

<script>
    if (document.getElementById('choices-multiple-remove-button')) {
        var element = document.getElementById('choices-multiple-remove-button');
        const example = new Choices(element, {
            removeItemButton: true
        });
    }

    if (document.querySelector('.datetimepicker')) {
        flatpickr('.datetimepicker', {
            allowInput: true
        }); // flatpickr
    }

</script>
