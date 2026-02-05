<main class="main-content mt-0">
    <div class="page-header align-items-start section-height-50 pt-5 pb-11 m-3 border-radius-lg"
        style="background-image: url('../../../assets/img/EOMDTM.png');">
        <span class="mask bg-gradient-dark opacity-6"></span>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-4">
                    <div class="card z-index-0">
                        <div class="card-body">
                            <h4 class="text-center">Login via OTP</h4>

                            <div x-data="{ otpSent: @entangle('otpSent').defer }">
                                <form wire:submit.prevent="sendOtp">
                                    <div class="mb-3">
                                        <input type="text" wire:model.lazy="contact" class="form-control"
                                            placeholder="Phone No. or Email for OTP">
                                        @error('contact') <small class="text-danger">{{ $message }}</small> @enderror
                                    </div>

                                    <div class="mb-3">
                                        <button type="submit" class="btn btn-primary w-100"
                                            wire:loading.attr="disabled"
                                            x-show="!otpSent"
                                            x-transition>
                                            Send OTP
                                        </button>

                                        <div x-show="otpSent" x-transition>
                                            <div class="d-flex justify-content-center mt-3">
                                                <template x-for="i in 5" :key="i">
                                                    <input maxlength="1" class="form-control mx-1 text-center" style="width: 2.5rem;"
                                                        :value="$wire.otp[i - 1]"
                                                        @input="$wire.set('otp.' + (i - 1), $event.target.value)">
                                                </template>
                                            </div>
                                            <div class="text-center text-muted mt-2">
                                                Resend OTP in <span x-text="$wire.countdown"></span> seconds
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
