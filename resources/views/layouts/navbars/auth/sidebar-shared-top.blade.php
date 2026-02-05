@php
    use Illuminate\Support\Facades\Route;
    $homeRoute = collect(['overview', 'dashboard', 'home'])->first(fn($r) => Route::has($r));
    $homeUrl   = $homeRoute ? route($homeRoute) : url('/');
    $isActive  = $homeRoute ? request()->routeIs($homeRoute) : request()->is('/');
@endphp

<li class="nav-item mt-2">
  <div class="nav-link text-uppercase text-xs font-weight-bolder text-dark opacity-7">
    Home
  </div>
</li>
