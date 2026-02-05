<li class="nav-item mt-2">
  <div class="nav-link text-uppercase text-xs font-weight-bolder text-dark opacity-7">My Sales</div>
</li>
<li class="nav-item">
  <a class="nav-link {{ request()->routeIs('vendor.milk.dashboard') ? 'active' : '' }}" href="{{ route('vendor.milk.dashboard') }}">
    <i class="ni ni-shop"></i><span class="nav-link-text ms-1">Milk Vendor Dashboard</span>
  </a>
</li>
<li class="nav-item">
  <a class="nav-link {{ request()->routeIs('vendor.orders.*') ? 'active' : '' }}" href="{{ route('vendor.orders.index') }}">
    <i class="ni ni-bullet-list-67"></i><span class="nav-link-text ms-1">Orders</span>
  </a>
</li>
