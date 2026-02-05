<li class="nav-item mt-2">
  <div class="nav-link text-uppercase text-xs font-weight-bolder text-dark opacity-7">My Deliveries</div>
</li>
<li class="nav-item">
  <a class="nav-link {{ request()->routeIs('deliveries.today') ? 'active' : '' }}" href="{{ route('deliveries.today') }}">
    <i class="ni ni-delivery-fast"></i><span class="nav-link-text ms-1">Today’s Deliveries</span>
  </a>
</li>