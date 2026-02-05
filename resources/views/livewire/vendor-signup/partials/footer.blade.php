@php
/** @var int|string $step */
$step = (int) $step; {{-- <- cast once --}}
$prev = max(1, $step - 1);
$next = min(3, $step + 1);
@endphp

@unless($step === 2) {{-- <- now this works even if query gave "2" --}}
<div class="d-flex justify-content-between px-3 pb-3 pt-1">
  @if($step > 1)
  <a class="btn btn-outline-secondary"
    href="{{ route('vendor.signup', ['step' => $prev, 'type' => $type]) }}">
    Back
  </a>
  @else
  <span></span>
  @endif

  @if($step < 3)
    <a class="btn btn-dark"
    href="{{ route('vendor.signup', ['step' => $next, 'type' => $type]) }}">
    Continue
    </a>
    @else
    <button type="submit" form="profileForm" class="btn btn-success">Submit</button>
    @endif
</div>
@endunless