@php
  use Illuminate\Support\Str;

  $hasAdminish = collect($roles)->contains(fn($r) => in_array($r, ['admin','zones-director','zones-head','zone-manager']));
  $hasDelivery = collect($roles)->contains(fn($r) => Str::startsWith($r, 'workman-delivery-boy'));
  $hasVendor   = collect($roles)->contains(fn($r) => $r === 'vendor' || Str::startsWith($r, 'vendor-'));

  if ($hasAdminish && ($hasDelivery || $hasVendor))      $myTitle = 'My Work';
  elseif ($hasAdminish)                                   $myTitle = 'My Work';
  elseif ($hasDelivery && !$hasVendor)                    $myTitle = 'My Deliveries';
  elseif ($hasVendor && !$hasDelivery)                    $myTitle = 'My Sales';
  else                                                    $myTitle = null;
@endphp

@if ($myTitle)
  @component('layouts.navbars.auth.partials.section', ['title'=>$myTitle,'key'=>'sec.my','defaultOpen'=>true])
    @php $rendered = []; @endphp
    @foreach ($roles as $role)
      @php $view = $roleSidebarMap[$role] ?? null; @endphp
      @if ($view && view()->exists($view) && empty($rendered[$view]))
        @include($view) {{-- role partial outputs <li> items only --}}
        @php $rendered[$view] = true; @endphp
      @endif
    @endforeach
  @endcomponent
@endif
