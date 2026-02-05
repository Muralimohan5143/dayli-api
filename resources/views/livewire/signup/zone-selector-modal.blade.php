<div x-data="{
    pincode: '',
    focusContinue() {
        if (this.pincode.length === 6) {
            // small delay to ensure Livewire model sync completes
            setTimeout(() => this.$refs.continueBtn.focus(), 100);
        }
    },
    focusPin() { this.$nextTick(() => this.$refs.pin?.focus()); }
}">
  <div
    x-show="$wire.show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-10"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-10"
    class="fixed inset-0 flex items-center justify-center bg-black/50 z-50"
    x-effect="$wire.show && focusPin()" 
    >


    <div class="bg-white rounded-3 shadow-md w-full max-w-md sm:max-w-sm p-4 md:p-5">
      <h5 class="fw-bold mb-3 text-center">Select Your Zone</h5>

      <!-- compact inner width like OTP card -->
      <div class="mx-auto w-full" style="max-width: 420px;">


        {{-- Pincode entry --}}
        <div class="mb-3">
          <label class="form-label">Enter Pincode</label>
          <input
            x-ref="pin"
            type="text"
            class="form-control @error('pincode') is-invalid @enderror"
            maxlength="6"
            wire:model.defer="pincode"
            x-model="pincode"
            x-on:input="focusContinue">
          @error('pincode') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <button
          class="btn btn-primary w-100 mb-3"
          wire:click="selectByPincode"
          x-ref="continueBtn">Continue with Pincode</button>

        {{-- Divider --}}
        <div class="text-center text-muted small mb-3">or</div>

        {{-- Location --}}
        <button class="btn btn-outline-secondary w-100"
          x-on:click="navigator.geolocation.getCurrentPosition(
                pos => $wire.selectByLocation(pos.coords.latitude, pos.coords.longitude),
                err => alert('Unable to fetch location: ' + err.message)
              )">
          Use Current Location
        </button>

      </div>
    </div>
  </div>
</div>