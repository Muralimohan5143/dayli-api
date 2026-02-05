<div class="d-flex justify-content-between px-3 pb-3 pt-1">
  @if($step > 1)
  {{-- Back button (outline wheat/orange tone) --}}
  <button class="btn btn-outline-warning" wire:click="goPrev">
    ‹ Back
  </button>
  @else
  <span></span>
  @endif

  @if($step === 2)
  {{-- Continue button (solid primary orange) --}}

  <button type="button"
    class="btn btn-warning"
    wire:click="goToNextStep"
    wire:loading.attr="disabled">
    CONTINUE ›
  </button>
  @elseif($step === 3)
  {{-- Submit button (green for success) --}}
  <button
    type="button"
    class="btn btn-success"
    wire:click="$dispatch('profile:submit')"
    wire:loading.attr="disabled">
    SUBMIT
  </button>
  @endif
</div>