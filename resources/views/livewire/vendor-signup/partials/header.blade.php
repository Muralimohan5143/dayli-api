@php
/** @var string $signupBg */
/** @var int $total */
/** @var int $current */
/** @var array $labels */

$icons = [
1 => '<i class="fa-solid fa-address-book"></i>',
2 => '<i class="fa-solid fa-file-signature"></i>',
3 => '<i class="fa-solid fa-user"></i>',
];
@endphp
<style>
    /* ---------- Dayli Theme Palette ---------- */
    :root {
        --wheat-light: #F5ECD9;
        --wheat-med: #F5D3A3;
        --wheat-deep: #CBB27A;
        --yellow-soft: #FFF7BF;
        --yellow-classic: #FCD34D;
        --yellow-gold: #D9A400;
        --orange-peach: #FFE1C6;
        --orange-dayli: #FB923C;
        --orange-burnt: #C2410C;
        --red-light: #FECACA;
        --red-ritual: #EF4444;
        --red-deep: #991B1B;
        --fest-gold: #FFD700;
        --fest-pink: #E91E63;
        --fest-teal: #00897B;
    }

    /* ---------- Background hero ---------- */
    .dayli-signup-hero {
        --bg: url('{{ $signupBg }}');
        min-height: 100vh;
        width: 100%;
        position: relative;
        background-image: var(--bg);
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        display: grid;
        place-items: center;
        padding: 32px 16px;
    }

    .dayli-signup-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(0, 0, 0, .28) 0%, rgba(0, 0, 0, .18) 30%, rgba(0, 0, 0, .28) 100%);
        pointer-events: none;
    }

    /* ---------- Glass card ---------- */
    .dayli-signup-card {
        position: relative;
        z-index: 1;
        width: min(92vw, 880px);
        border-radius: 18px;
        border: 1px solid rgba(203, 178, 122, .45);
        /* Deep Wheat */
        background: color-mix(in oklab, var(--wheat-light) 65%, transparent);
        backdrop-filter: blur(8px) saturate(120%);
        -webkit-backdrop-filter: blur(8px) saturate(120%);
        box-shadow: 0 10px 25px rgba(0, 0, 0, .18), 0 2px 6px rgba(0, 0, 0, .08);
    }

    .dayli-card-body {
        padding: 22px;
    }

    /* ---------- Stepper ---------- */
    /* ---------- Stepper ---------- */
    .stepper {
        --circle: 38px;
        /* circle diameter */
        --pad: calc(var(--circle) * 0.75);
        /* how much the track should stop short on each side */
        --track-h: 4px;

        position: relative;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 18px;
    }

    /* track that NEVER goes beyond the circles */
    .stepper .track,
    .stepper .fill {
        position: absolute;
        top: calc(var(--circle) / 2 - var(--track-h) / 2);
        left: var(--pad);
        right: var(--pad);
        height: var(--track-h);
        border-radius: 999px;
    }

    .stepper .track {
        background: #e5e7eb;
    }

    /* fill uses scaleX so we don't have to do calc(width - pads) math */
    .stepper .fill {
        background: #FCD34D;
        /* yellow */
        transform-origin: left center;
        transform: scaleX(0);
        /* set inline below */
        transition: transform .3s ease;
    }

    .step {
        text-align: center;
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 0 0 auto;
    }

    .step-circle {
        width: var(--circle);
        height: var(--circle);
        border-radius: 50%;
        background: #6b7280;
        /* default gray */
        color: #2b2b2b;
        display: grid;
        place-items: center;
        transition: all .25s ease;
        box-shadow: 0 1px 2px rgba(0, 0, 0, .06);
    }

    .step-circle i {
        color: #2b2bff;
        font-size: 18px;
    }

    /* icon color/size */
    .step-circle.complete {
        background: #D9A400;
    }

    /* gold */
    .step-circle.active {
        background: #FB923C;
        /* orange */
        box-shadow: 0 0 0 8px rgba(251, 146, 60, .18);
        transform: scale(1.15);
    }

    .step-label {
        margin-top: 8px;
        font-size: 14px;
        color: #6b7280;
    }

    .step-label.active {
        color: #111827;
        font-weight: 600;
    }
</style>

<div class="dayli-signup-hero">
    <div class="dayli-signup-card">
        <div class="dayli-card-body">

            {{-- Title (optional) --}}
            <h1 class="h4 mb-3 text-dark fw-bold">Vendor / Workman Signup</h1>

            {{-- Stepper --}}
            {{-- Stepper --}}
            <div class="stepper" role="list" aria-label="Signup steps">
                {{-- the track + fill.  Fill is scaled to progress; the track stops before the circles --}}
                <div class="track"></div>
                <div class="fill" style="transform: scaleX({{ $pct/100 }});"></div>
                @for ($i = 1; $i <= $total; $i++)
                    @php
                    $state=$i < $current ? 'complete' : ($i===$current ? 'active' : '' );
                    $icons=[
                    1=> '📇', // Contact icon
                    2 => '📝', // Contract / signature
                    3 => '👤', // Profile / user check
                    ];
                    @endphp

                    <div class="step" role="listitem" aria-current="{{ $i === $current ? 'step' : 'false' }}">
                        <div class="step-circle {{ $state }}">
                            {!! $icons[$i] !!}
                        </div>
                        <div class="step-label {{ $state }}">{{ $labels[$i-1] }}</div>
                    </div>
                    @endfor
            </div>
