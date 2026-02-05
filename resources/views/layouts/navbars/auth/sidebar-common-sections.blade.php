@component('layouts.navbars.auth.partials.section', ['title'=>'Subscriptions','key'=>'sec.subs','defaultOpen'=>false])
  <li class="nav-item">
    <a href="{{ Route::has('subscriptions.index') ? route('subscriptions.index') : '#' }}"
       class="nav-link {{ request()->routeIs('subscriptions.*') ? 'active' : '' }}">
      <i class="ni ni-collection"></i>
      <span class="nav-link-text ms-1">Manage Subscriptions</span>
    </a>
  </li>
@endcomponent

@component('layouts.navbars.auth.partials.section', ['title'=>'Services','key'=>'sec.services','defaultOpen'=>false])
  <li class="nav-item">
    <a href="{{ Route::has('services.index') ? route('services.index') : '#' }}"
       class="nav-link {{ request()->routeIs('services.*') ? 'active' : '' }}">
      <i class="ni ni-briefcase-24"></i>
      <span class="nav-link-text ms-1">Browse Services</span>
    </a>
  </li>
@endcomponent

@component('layouts.navbars.auth.partials.section', ['title'=>'Orders','key'=>'sec.orders','defaultOpen'=>false])
  <li class="nav-item">
    <a href="{{ Route::has('orders.index') ? route('orders.index') : '#' }}"
       class="nav-link {{ request()->routeIs('orders.*') ? 'active' : '' }}">
      <i class="ni ni-bullet-list-67"></i>
      <span class="nav-link-text ms-1">My Orders</span>
    </a>
  </li>
@endcomponent

@component('layouts.navbars.auth.partials.section', ['title'=>'Utilities','key'=>'sec.utils','defaultOpen'=>false])
  <li class="nav-item">
    <a href="{{ Route::has('myday.index') ? route('myday.index') : '#' }}"
       class="nav-link {{ request()->routeIs('myday.index') ? 'active' : '' }}">
      <i class="ni ni-time-alarm"></i>
      <span class="nav-link-text ms-1">My Day</span>
    </a>
  </li>
  <li class="nav-item">
    <a href="{{ Route::has('settings') ? route('settings') : '#' }}"
       class="nav-link {{ request()->routeIs('settings') ? 'active' : '' }}">
      <i class="ni ni-settings-gear-65"></i>
      <span class="nav-link-text ms-1">Settings</span>
    </a>
  </li>
@endcomponent
