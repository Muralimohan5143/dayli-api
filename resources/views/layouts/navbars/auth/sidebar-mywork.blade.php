@php
  // Decide title based on roles:
  $hasAdminish = collect($roles)->contains(fn($r) => in_array($r, ['admin','zones-director','zones-head','zone-manager']));
  $hasDelivery = collect($roles)->contains(fn($r) => Str::startsWith($r, 'workman-delivery-boy'));
  $hasVendor   = collect($roles)->contains(fn($r) => $r === 'vendor' || Str::startsWith($r, 'vendor-'));

  if ($hasAdminish && ($hasDelivery || $hasVendor)) {
      $myTitle = 'My Work'; // mixed roles => neutral title
  } elseif ($hasAdminish) {
      $myTitle = 'My Work';
  } elseif ($hasDelivery && !$hasVendor) {
      $myTitle = 'My Deliveries';
  } elseif ($hasVendor && !$hasDelivery) {
      $myTitle = 'My Sales';
  } else {
      $myTitle = null; // no special work block (e.g., pure customer)
  }
@endphp

@if ($myTitle)
  {{-- Section label --}}
  <li class="nav-item mt-2">
    <div class="nav-link text-uppercase text-xs font-weight-bolder text-dark opacity-7">{{ $myTitle }}</div>
  </li>

  {{-- Merge role-specific content under this title --}}
  @php $renderedPartials = []; @endphp
  @foreach ($roles as $role)
    @php $view = $roleSidebarMap[$role] ?? null; @endphp
    @if ($view && view()->exists($view) && empty($renderedPartials[$view]))
      @include($view)
      @php $renderedPartials[$view] = true; @endphp
    @endif
  @endforeach
@endif
