@php
    use Illuminate\Support\Facades\Route;

    $myDayRoute   = collect(['myday.index','my-day','myday','my_day'])->first(fn($r) => Route::has($r));
    $myDayUrl     = $myDayRoute ? route($myDayRoute) : '#';
    $myDayActive  = $myDayRoute ? request()->routeIs($myDayRoute) : false;

    $settingsRoute  = collect(['settings','settings.index','profile.settings','user.settings'])->first(fn($r) => Route::has($r));
    $settingsUrl    = $settingsRoute ? route($settingsRoute) : '#';
    $settingsActive = $settingsRoute ? request()->routeIs($settingsRoute) : false;
@endphp

<li class="nav-item mt-3">
  <div class="nav-link text-uppercase text-xs font-weight-bolder text-dark opacity-7">Utilities</div>
</li>

<li class="nav-item">
  <a href="{{ $myDayUrl }}" class="nav-link {{ $myDayActive ? 'active' : '' }}">
    <i class="ni ni-time-alarm"></i>
    <span class="nav-link-text ms-1">My Day</span>
  </a>
</li>

<li class="nav-item">
  <a href="{{ $settingsUrl }}" class="nav-link {{ $settingsActive ? 'active' : '' }}">
    <i class="ni ni-settings-gear-65"></i>
    <span class="nav-link-text ms-1">Settings</span>
  </a>
</li>
