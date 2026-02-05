{{-- resources/views/livewire/signup/vendor-signup-wizard.blade.php --}}
<div>
  @php
  $align = $step === 1 ? 'left' : ($step === 2 ? 'center' : 'right');
  @endphp

  <div class="dayli-signup-hero">
    {{-- header sits inside hero, no bg passed --}}
    <div class="edge-gap">
      @livewire(
      'signup.vendor-signup-header',
      [
      'step' => $step,
      'signupBg' => asset('assets/img/bg/vernazza.jpg'),
      ],
      key('vendor-header-'.$step)
      )
    </div>
    <div id="signup-shell">
      <div class="signup-container">
        {{-- step bodies here --}}
      </div>
    </div>

    {{-- main card --}}

    <div class="step-section {{ $align }}">
      <div class="section-inner">
        <div class="dayli-signup-card">
          <div class="dayli-card-body">


            @if ($step === 1)
            @livewire('auth.signin', [
            'embedded' => true,
            'redirectUrl' => route('vendor.signup', ['step' => 2, 'type' => $type]),
            ])
            @elseif ($step === 2)
            {{-- Step 2: Contract Details --}}
            @if (empty($zoneId))
            @livewire('signup.zone-selector-modal')
            @else
            @livewire('signup.vendor-contract-details', [
            'zoneId' => $zoneId,
            'vendorId'=> $vendorId ?? auth()->id(),
            'type' => $type, // optional legacy
            ], key('contract-2'))
            @endif
            @elseif ($step === 3)
            @livewire(
            'signup.vendor-profile-details',
            [
            'type' => $type,
            'userId' => $pendingUserId, {{-- NEW --}}
            ],
            key('profile-'.$type.'-'.$pendingUserId)
            )
            @endif

            {{-- footer buttons --}}
            @livewire('signup.vendor-signup-footer', ['step' => $step, 'type' => $type], key('footer-'.$step))

          </div>
        </div>
      </div>
    </div>
  </div>
</div>