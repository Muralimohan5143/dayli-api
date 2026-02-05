@extends('layouts.app')

@section('content')
<main class="main-content mt-0">
    <div class="page-header align-items-start section-height-50 pt-5 pb-11 m-3 border-radius-lg"
        style="background-image: url('../../../assets/img/EOMDTM.png');">
        <span class="mask bg-gradient-dark opacity-6"></span>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-4">
                    <!-- White Panel -->
                    <div class="card z-index-0 pb-4 shadow">
                        <div class="card-body">
                            <h4 class="text-center mb-4">Login via OTP</h4>

                            {{-- OTP Login Form --}}
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
                                    window.addEventListener('start-otp-countdown', () => {
                                        this.countdown = 30;
                                        this.otpSent = true;
                                        this.allowResend = false;
                                        this.startCountdown();
                                        this.sendingOtp = false;
                                        setTimeout(() => document.getElementById('otp-1')?.focus(), 100);
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
                                        this.checkVerifyEnable();
                                    }
                                },
                                handleBackspace(i, value) {
                                    if (!value && i > 0) {
                                        document.getElementById('otp-' + i)?.focus();
                                    }
                                },
                                checkVerifyEnable() {
                                    this.verifyEnabled = this.otpDigits.every(d => d.length === 1);
                                }
                            }">

                                <form wire:submit.prevent="sendOtp">
                                    <div class="mb-3">
                                        <input type="text" wire:model.defer="contact" name="contact" class="form-control" placeholder="Phone or Email" />
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
                                                <template x-if="otpSent && countdown > 0">
                                                    <span>OTP Sent!</span>
                                                </template>
                                                <template x-if="!otpSent || countdown === 0">
                                                    <span>Send OTP</span>
                                                </template>
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
                                                    autocomplete="off">
                                            </template>
                                        </div>

                                        <div class="text-center mt-3">
                                            <button type="button"
                                                wire:click="verifyOtp"
                                                wire:loading.attr="disabled"
                                                wire:target="verifyOtp"
                                                class="btn btn-success"
                                                :disabled="verifyingOtp || !verifyEnabled"
                                                @click="verifyingOtp = true">

                                                <template x-if="!verifyingOtp">
                                                    <span>Verify OTP</span>
                                                </template>
                                                <template x-if="verifyingOtp">
                                                    <span x-text="verifyButtonText"></span>
                                                </template>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="text-center mt-3 text-muted" x-show="otpSent && countdown > 0" x-text="timerText"></div>

                                    <div class="text-center mt-3">
                                        @error('contact')<div class="text-danger">{{ $message }}</div>@enderror
                                        @error('otp')<div class="text-danger mt-2">{{ $message }}</div>@enderror

                                        @if (app()->environment('local'))
                                            <div class="text-center text-danger mt-3">
                                                Generated OTP (dev only): {{ $gen_otp }}
                                            </div>
                                        @endif
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Footer Links -->
                        <div class="card-footer text-center bg-white border-0 mt-3">
                            <p class="mb-0">
                                Don’t have an account?  
                                <a href="/register-vendor" class="text-primary fw-bold">Sign up as Vendor</a> / 
                                <a href="/register-workman" class="text-primary fw-bold">Workman</a>
                            </p>
                        </div>

                    </div>
                </div>
            </div>
        </div>
</main>
@endsection
