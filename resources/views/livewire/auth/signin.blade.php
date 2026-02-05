<div>
    {{-- resources/views/livewire/auth/signin.blade.php --}}

@php
  // treat missing prop as false
  $embedded = $embedded ?? false;
@endphp

{{-- ========== FULL PAGE WRAPPER (ONLY when NOT embedded) ========== --}}
@unless($embedded)
<main class="main-content mt-0">
  <div class="page-header align-items-start section-height-50 pt-5 pb-11 m-3 border-radius-lg"
       style="background-image: url('../../../assets/img/EOMDTM.png');">
    <span class="mask bg-gradient-dark opacity-6"></span>
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-4">
          <div class="card z-index-0">
            <div class="card-body">
              @include('livewire.auth.partials.signin_form')
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
@endunless

{{-- ========== EMBEDDED MODE (wizard) ========== --}}
@isset($embedded)
  @if($embedded)
    @include('livewire.auth.partials.signin_form')
  @endif
@endisset
</div>
