<div class="d-flex justify-content-between bg-light p-3 rounded mb-4 align-items-center">
    {{-- Step 1 --}}
    <div class="text-center flex-fill">
        <div class="{{ $step === 1 ? 'fw-bold text-primary' : 'text-muted' }}">
            {{ $step > 1 ? '✔' : 'Step 1' }}
        </div>
        <small>Login</small>
    </div>

    <div class="flex-fill border-top" style="opacity:.4"></div>

    {{-- Step 2 --}}
    <div class="text-center flex-fill">
        <div class="{{ $step === 2 ? 'fw-bold text-primary' : 'text-muted' }}">
            {{ $step > 2 ? '✔' : 'Step 2' }}
        </div>
        <small>Service & Category</small>
    </div>

    <div class="flex-fill border-top" style="opacity:.4"></div>

    {{-- Step 3 --}}
    <div class="text-center flex-fill">
        <div class="{{ $step === 3 ? 'fw-bold text-primary' : 'text-muted' }}">Step 3</div>
        <small>Profile Setup</small>
    </div>
</div>
