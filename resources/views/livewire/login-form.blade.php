<div class="container mt-5" style="max-width: 400px;">
    <div class="card shadow-sm">
        <div class="card-body">
            <h4 class="text-center mb-4">Login</h4>

            <form wire:submit.prevent="{{ $otpSent ? 'verifyOtp' : 'sendOtp' }}">
                <div class="mb-3">
                    <input type="text" wire:model="loginInput" class="form-control" placeholder="Phone No. or Email for OTP" required>
                    @error('loginInput') <span class="text-danger">{{ $message }}</span> @enderror
                </div>

                @if (!$otpSent)
                <button class="btn btn-primary w-100" type="submit">Send OTP</button>
                @endif

                @if ($otpSent)
                <div x-data="{ show: true }" x-show="show" x-transition.duration.600ms class="mt-4">
                    <div class="d-flex justify-content-between mb-2">
                        @foreach ($otp as $index => $value)
                        <input type="text" maxlength="1" wire:model.lazy="otp.{{ $index }}"
                            class="form-control text-center mx-1 otp-input" style="width: 50px;">
                        @endforeach
                    </div>

                    <button type="submit" class="btn btn-success w-100">Verify OTP</button>

                    <div class="text-center text-muted mt-2">
                        <small>Resend OTP in <span id="timerDisplay">{{ $resendCountdown }}</span> seconds</small>
                    </div>
                </div>
                @endif

                @if (session('error'))
                <div class="alert alert-danger mt-3">{{ session('error') }}</div>
                @endif
                @if (session('success'))
                <div class="alert alert-success mt-3">{{ session('success') }}</div>
                @endif
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('start-countdown', function() {
        let remaining = 30;
        const timerEl = document.getElementById('timerDisplay');
        const interval = setInterval(() => {
            remaining--;
            if (timerEl) timerEl.textContent = remaining;
            if (remaining <= 0) clearInterval(interval);
        }, 1000);
    });
</script>
@endpush