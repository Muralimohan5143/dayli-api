{{-- Livewire OTP login — no <form> submit, no page GET --}}
<div>
  <label class="form-label fw-semibold mb-1">Login via OTP</label>

  {{-- Contact --}}
  <div class="mb-2">
    <input
      type="text"
      class="form-control @error('contact') is-invalid @enderror"
      placeholder="Phone or Email"
      {{-- IMPORTANT: no name attr (prevents outer GET like /vendor-signup?contact=...) --}}
      wire:model.defer="contact"
      onkeydown="if (event.key==='Enter') { event.preventDefault(); $wire.sendOtp(); }"
      autocomplete="one-time-code"
    >
    @error('contact') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  {{-- Send OTP --}}
  <button
    type="button"
    class="btn btn-primary"
    wire:click="sendOtp"
    wire:target="sendOtp"
    wire:loading.attr="disabled"
  >
    <span wire:loading.remove wire:target="sendOtp">Send OTP</span>
    <span wire:loading wire:target="sendOtp">Sending OTP…</span>
  </button>

  {{-- OTP UI (show after send) --}}
  @if (!empty($otpSent))
    <div class="mt-3">
      <div class="d-flex gap-2">
        @for ($i = 0; $i < 5; $i++)
          <input
            type="text"
            maxlength="1"
            class="form-control text-center"
            style="width:48px"
            wire:model.defer="otp.{{ $i }}"
            oninput="if (this.value && this.nextElementSibling) this.nextElementSibling.focus()"
            onkeydown="if (event.key==='Enter') { event.preventDefault(); $wire.verifyOtp(); }"
          >
        @endfor
      </div>
      @error('otp') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>

    {{-- Verify --}}
    <button
      type="button"
      class="btn btn-success mt-3"
      wire:click="verifyOtp"
      wire:target="verifyOtp"
      wire:loading.attr="disabled"
    >
      <span wire:loading.remove wire:target="verifyOtp">Verify OTP</span>
      <span wire:loading wire:target="verifyOtp">Verifying…</span>
    </button>
  @endif

  {{-- Dev-only helper --}}
  @env('local')
    @if (!empty($gen_otp))
      <div class="form-text mt-2">Generated OTP (dev only): {{ $gen_otp }}</div>
    @endif
  @endenv
</div>
