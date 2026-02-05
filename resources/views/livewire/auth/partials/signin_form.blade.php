{{-- resources/views/livewire/auth/partials/_signin_form.blade.php --}}

<h4 class="text-center mb-4">Signup via OTP</h4>

<div x-data="{
    otpSent: @entangle('otpSent'),
    countdown: @entangle('countdown'),
    genOtp: @entangle('gen_otp'),
    allowResend: false,
    sendingOtp: false,
    verifyingOtp: false,
    verifyButtonText: 'Verifying...',
    otpDigits: ['', '', '', '', ''],
    timerText: '',
    verifyEnabled: false,
    intervalId: null,
    init() {
  const focusFirstOtp = () =>
    this.$nextTick(() => requestAnimationFrame(() => document.getElementById('otp-1')?.focus()));

  // Fired after Send/Resend OTP
  window.addEventListener('start-otp-countdown', (e) => {
    // Reset UI for a fresh code
    this.otpDigits     = ['', '', '', '', ''];
    this.verifyEnabled = false;
    this.verifyingOtp  = false;
    $wire.set('otp', ['', '', '', '', '']);

    // Countdown & states
    this.countdown   = (e?.detail?.expiresIn ?? 30);
    this.otpSent     = true;
    this.allowResend = false;
    this.startCountdown();
    this.sendingOtp  = false;

    focusFirstOtp();
  });

  // Fired when OTP is wrong / expired
  window.addEventListener('otp-invalid', () => {
    // DO NOT touch countdown or otpSent here
    this.verifyingOtp     = false;
    this.verifyEnabled    = false;
    this.verifyButtonText = 'Verifying...';
    this.otpDigits        = ['', '', '', '', ''];
    $wire.set('otp', ['', '', '', '', '']);

    focusFirstOtp();
  });

  window.addEventListener('redirecting', () => {
    this.verifyButtonText = 'Redirecting...';
  });
},
    startCountdown() {
      this.timerText = `Resend OTP in ${this.countdown} seconds`;
      if (this.intervalId) clearInterval(this.intervalId);
      this.intervalId = setInterval(() => {
        if (this.countdown > 0) {
          this.countdown--;
          this.timerText = `Resend OTP in ${this.countdown} seconds`;
        } else {
          clearInterval(this.intervalId);
          this.timerText = '';
          this.allowResend = true;
        }
      }, 1000);
    },
    setOtp(i, value) {
      this.otpDigits[i] = value;
      $wire.set('otp.' + i, value);
      if (value.length === 1) {
        if (i < 4) document.getElementById('otp-' + (i + 2))?.focus();
        this.verifyEnabled = this.otpDigits.every(d => d.length === 1);
      }
    },
    handleBackspace(i, value) {
      if (!value && i > 0) {
        document.getElementById('otp-' + i)?.focus();
      }
    }
}">
  <div class="d-flex justify-content-center">
    <div class="card shadow-sm" style="max-width: 420px; width: 100%;">
      <div class="card-body">
        <form wire:submit.prevent="sendOtp">

          <div class="mb-3" x-data x-init="$nextTick(() => document.getElementById('contactInput')?.focus())">
            <input id="contactInput"
              type="text"
              wire:model.defer="contact"
              name="contact"
              class="form-control"
              placeholder="Phone or Email" />
            @error('contact')<div class="text-danger mt-1">{{ $message }}</div>@enderror
          </div>

          <div class="mb-3 position-relative">
            <button type="submit"
              class="btn w-100 text-white d-flex justify-content-center align-items-center"
              style="min-height: 42px;"
              :class="(!allowResend && otpSent) ? 'bg-secondary border-0' : 'btn-primary'"
              :disabled="!allowResend && otpSent"
              @click="sendingOtp = true">
              <span wire:loading wire:target="sendOtp">
                <i class="fas fa-spinner fa-spin me-2"></i> Sending OTP...
              </span>
              <span wire:loading.remove wire:target="sendOtp">
                <template x-if="otpSent && countdown > 0"><span>OTP Sent!</span></template>
                <template x-if="!otpSent || countdown === 0"><span>Send OTP</span></template>
              </span>
            </button>
          </div>



          <div x-show="otpSent" x-transition>
            <div class="d-flex justify-content-center mt-3">
              <template x-for="i in 5" :key="i">
                <input maxlength="1"
                  class="form-control mx-1 text-center"
                  style="width: 2.5rem;"
                  :id="'otp-' + i"
                  x-model="otpDigits[i - 1]"
                  @input="setOtp(i - 1, $event.target.value)"
                  @keydown.backspace="handleBackspace(i - 1, $event.target.value)"
                  @keydown.enter.prevent.stop="if (verifyEnabled) { $wire.verifyOtp() }" <!-- add this -->
                autocomplete="off">
              </template>
            </div>

            <div class="text-center mt-3">
              <button id="verify-btn" type="button"
                wire:click="verifyOtp"
                wire:loading.attr="disabled"
                wire:target="verifyOtp"
                class="btn btn-success"
                :disabled="verifyingOtp || !verifyEnabled"
                @click="verifyingOtp = true"
                @keydown.enter.prevent="$wire.verifyOtp()" <!-- add -->
                <!-- @keydown.space.prevent="$wire.verifyOtp()"> add -->
                <span wire:loading wire:target="verifyOtp">
                  <i class="fas fa-spinner fa-spin me-2"></i> verifying OTP...
                </span>
                <span wire:loading.remove wire:target="verifyOtp">
                  <template x-if="!verifyingOtp"><span>Verify OTP</span></template>
                  <template x-if="verifyingOtp"><span x-text="verifyButtonText"></span></template>
              </button>
            </div>
          </div>

          <div class="text-center mt-3 text-muted"
            x-show="otpSent && countdown > 0"
            x-text="timerText"></div>

          @error('otp')<div class="text-danger text-center mt-2">{{ $message }}</div>@enderror

          @if (app()->environment('local'))
          <div class="text-center text-danger mt-3">
            Generated OTP (dev only): {{ $gen_otp }}
          </div>
          @endif

          {{-- Only show this link on the full /login page; hide in wizard --}}
          @if(!($embedded ?? false))
          <div class="mt-2 text-center">
            <a href="{{ route('vendor.signup') }}" class="text-primary fw-semibold text-decoration-underline">
              Sign up as Vendor / Workman
            </a>
          </div>
          @endif








        </form>

      </div>
    </div>
  </div>
</div>