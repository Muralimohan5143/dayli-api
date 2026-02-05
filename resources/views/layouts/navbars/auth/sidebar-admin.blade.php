{{-- Operations --}}
<li class="nav-item mt-2">
  <div class="sb-section-title">Operations</div>
</li>
<li class="nav-item">
  <a class="nav-link sb-item {{ request()->routeIs('sub-change-requests.*') ? 'active' : '' }}"
     href="{{ route('sub-change-requests.grouped') }}">
    <i class="sb-icon ni ni-ruler-pencil"></i>
    <span class="nav-link-text ms-1">CR Change Requests</span>
  </a>
</li>
<li class="nav-item">
  <a class="nav-link sb-item {{ request()->routeIs('sub-delivery-actuals.*') ? 'active' : '' }}"
     href="{{ route('sub-delivery-actuals.index') }}">
    <i class="sb-icon ni ni-delivery-fast"></i>
    <span class="nav-link-text ms-1">DA Delivery Actuals</span>
  </a>
</li>
<li class="nav-item">
  <a class="nav-link sb-item {{ request()->routeIs('zones.*') ? 'active' : '' }}"
     href="{{ route('zones.index') }}">
    <i class="sb-icon ni ni-world"></i>
    <span class="nav-link-text ms-1">Zones</span>
  </a>
</li>

{{-- User Management --}}
<li class="nav-item mt-3">
  <div class="sb-section-title">User Management</div>
</li>
<li class="nav-item">
  <a class="nav-link sb-item {{ request()->routeIs('users.*') ? 'active' : '' }}"
     href="{{ route('users.index') }}">
    <i class="sb-icon ni ni-single-02"></i>
    <span class="nav-link-text ms-1">Users</span>
  </a>
</li>
<li class="nav-item">
  <a class="nav-link sb-item {{ request()->routeIs(['roles.*','permissions.*']) ? 'active' : '' }}"
     href="{{ route('roles.index') }}">
    <i class="sb-icon ni ni-key-25"></i>
    <span class="nav-link-text ms-1">Roles & Permissions</span>
  </a>
</li>

{{-- Technology --}}
<li class="nav-item mt-3">
  <div class="sb-section-title">Technology</div>
</li>
<li class="nav-item">
  <a class="nav-link sb-item {{ request()->routeIs('tech.email-status') ? 'active' : '' }}"
     href="{{ route('tech.email-status') }}">
    <i class="sb-icon ni ni-email-83"></i>
    <span class="nav-link-text ms-1">Email Status</span>
  </a>
</li>
<li class="nav-item">
  <a class="nav-link sb-item {{ request()->routeIs('tech.platform-health') ? 'active' : '' }}"
     href="{{ route('tech.platform-health') }}">
    <i class="sb-icon ni ni-settings"></i>
    <span class="nav-link-text ms-1">Platform Health</span>
  </a>
</li>
