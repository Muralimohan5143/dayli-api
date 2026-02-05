<div>
  @php
  // ---- Inputs expected (fallbacks provided) ----
  $signupBg = $signupBg ?? asset('assets/img/bg/vernazza.jpg');
  $labels = $labels ?? ['Contact Details','Contract Details','Profile Details'];
  $total = max(1, (int)($total ?? count($labels)));
  $rawStep = isset($current) ? (int)$current : (isset($step) ? (int)$step : 1);
  $current = max(1, min($rawStep, $total));
  $progressPct = $total > 1 ? (($current - 1) / ($total - 1)) * 100 : 0;
  $progressPct = $current === $total ? 100 : max(0, min(100, $progressPct));
  @endphp

  <style>
    .dayli-signup-hero {
      --bg: url('{{ $signupBg }}');
      background-image: var(--bg);
      padding-top: calc(28px + env(safe-area-inset-top, 0px));
      padding-bottom: 10px;
      /* tiny breathing at bottom of the bar */
    }

    .edge-gap {
      width: 100%;
      max-width: 1200px;
      margin: 0 auto;
      /* centers the stepper like your price list */
      padding-inline: 0;
      /* already have page gutters from .dayli-signup-hero */
    }

    .dayli-signup-card {
      position: relative;
      z-index: 1;
      width: min(92vw, 1500px);
      margin: 0 auto;
      border-radius: 18px;
      border: 1px solid rgba(203, 178, 122, .45);
      background: color-mix(in oklab, var(--wheat-light, #fff5e6) 65%, transparent);
      backdrop-filter: blur(8px) saturate(120%);
      -webkit-backdrop-filter: blur(8px) saturate(120%);
      box-shadow: 0 10px 25px rgba(0, 0, 0, .18), 0 2px 6px rgba(0, 0, 0, .08);
    }

    .dayli-card-body {
      padding: 40px 20px;
    }


    /* ---- Stepper ---- */
    .stepper {
      /* geometry */
      --circle: 38px;
      /* base dot size */
      --scale: 1.15;
      /* active circle scale */
      --halo: 8px;
      /* active halo (box-shadow) */
      --gap: 2px;
      /* extra breathing room */
      --edge: calc((var(--circle) * var(--scale)) / 2 + var(--halo) + var(--gap));
      --track-h: 4px;

      position: relative;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 18px;
      width: 100%;
    }

    /* Bar is clipped and inset by the MAX footprint (scaled circle + halo) */
    .stepper .bar {
      position: absolute;
      top: calc(var(--circle)/2 - var(--track-h)/2);
      left: var(--edge);
      right: var(--edge);
      height: var(--track-h);
      border-radius: 999px;
      overflow: hidden;
      /* prevents any overshoot */
      z-index: 0;
      /* keep behind dots */
    }

    .stepper .track,
    .stepper .fill {
      position: absolute;
      inset: 0;
      height: 100%;
      border-radius: inherit;
    }

    .stepper .track {
      background: #e5e7eb;
    }

    .stepper .fill {
      background: #FCD34D;
      width: 0%;
      transition: width .25s ease;
    }

    .step {
      text-align: center;
      z-index: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .step-circle {
      width: var(--circle);
      height: var(--circle);
      border-radius: 50%;
      background: #0f172a;
      color: #fff;
      display: grid;
      place-items: center;
      font-size: 18px;
      transition: transform .2s ease;
    }

    .step-circle.complete {
      background: #111827;
    }

    .step-circle.active {
      background: #FB923C;
      /* use same halo var as in edge calc */
      box-shadow: 0 0 0 var(--halo) rgba(251, 146, 60, .18);
      transform: scale(var(--scale));
    }

    .step-label {
      margin-top: 8px;
      font-size: 14px;
      color: #000;
      font-weight: 700;
    }

    .step-label.active {
      color: #000;
      font-weight: 800;
    }
  </style>
  <div class="stepper" role="list" aria-label="Signup steps">
    <div class="bar">
      <div class="track"></div>
      <!-- force 100% on last step to avoid rounding -->
      <div class="fill" style="width: {{ ($current === $total) ? '100' : number_format($progressPct,4,'.','') }}%;"></div>
    </div>

    @php $icons=[1=>'📇',2=>'📝',3=>'👤']; @endphp
    @for ($i = 1; $i <= $total; $i++)
      @php $state=$i < $current ? 'complete' : ($i===$current ? 'active' : '' ); @endphp
      <div class="step" role="listitem" aria-current="{{ $i === $current ? 'step' : 'false' }}">
      <div class="step-circle {{ $state }}">{!! $icons[$i] ?? '•' !!}</div>
      <div class="step-label {{ $state }}">{{ $labels[$i-1] ?? "Step $i" }}</div>
  </div>
  @endfor
</div>
</div>