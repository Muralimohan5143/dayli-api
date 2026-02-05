@php
/** Inputs from route */
/** @var int $step */
/** @var string $type */

$signupBg = asset('assets/img/bg/vernazza.jpg');

// Stepper math
$total = 3;
$current = max(1, min((int)$step, $total));
$labels = ['Contact Details', 'Contract Details', 'Profile Details'];
$pct = $total > 1 ? (($current - 1) / ($total - 1)) * 100 : 0;
@endphp

{{-- HEADER (stepper with icons, no outer rails) --}}
@include('livewire.vendor-signup.partials.header', [
'signupBg' => $signupBg,
'total' => $total,
'current' => $current,
'labels' => $labels,
'pct' => $pct,
])

{{-- ===== Step 1: Embedded Login (one instance) ===== --}}
@if ($current === 1)
{{-- hard-stop Enter bubbling even if an outer <form> exists --}}
<div class="container mt-3"
    onkeydown="if (event.key==='Enter') { event.preventDefault(); event.stopPropagation(); return false; }">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-xl-5">
            <div class="card shadow-sm" style="border-radius:1rem;">
                <div class="card-body">
                    @livewire('auth.login-form', [
                    'embedded' => true, // hide hero/bg
                    'redirectUrl' => route('vendor.signup', ['step' => 2, 'type' => $type ?? null]),
                    ])
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ===== Middle content (Steps 2 & 3) ===== --}}
<div class="px-3 pb-3">
    @if ($current === 2)
    @switch($type)
    @case('milk_dairy') @include('vendor-signup.steps.vendor-milk') @break
    @case('vegetables') @include('vendor-signup.steps.vendor-veg') @break
    @case('fruits') @include('vendor-signup.steps.vendor-fruits') @break
    @case('beverages') @include('vendor-signup.steps.vendor-beverages') @break
    @case('bakery_snacks') @include('vendor-signup.steps.vendor-bakery_snacks') @break
    @case('fish_seafood') @include('vendor-signup.steps.vendor-fish_seafood') @break
    @case('meat') @include('vendor-signup.steps.vendor-meat') @break
    @case('flowers') @include('vendor-signup.steps.vendor-flowers') @break
    @case('groceries') @include('vendor-signup.steps.vendor-groceries') @break
    @case('puja_samagri') @include('vendor-signup.steps.vendor-puja_samagri') @break
    @case('chaats_quick_snacks') @include('vendor-signup.steps.vendor-chaats_quick_snacks') @break
    @case('sweets_confectionery') @include('vendor-signup.steps.vendor-sweets_confectionery') @break
    @case('health_packs') @include('vendor-signup.steps.vendor-health_packs') @break
    @default
    <div class="alert alert-warning mb-0">Unknown vendor type “{{ $type }}”.</div>
    @endswitch

    @elseif ($current === 3)
    @include('livewire.vendor-signup.steps.profile')
    @endif
</div>

{{-- FOOTER (Back/Continue) --}}
@include('livewire.vendor-signup.partials.footer', ['step' => $step, 'type' => $type])