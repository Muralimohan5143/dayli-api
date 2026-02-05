@php $homeActive = request()->routeIs('overview'); @endphp
@component('layouts.navbars.auth.partials.section', ['title'=>'Home','key'=>'sec.home','defaultOpen'=>true])
  <li class="nav-item">
    <a href="{{ route('overview') }}" class="nav-link {{ $homeActive ? 'active' : '' }}">
      <i class="ni ni-tv-2"></i>
      <span class="nav-link-text ms-1">Overview</span>
    </a>
  </li>
@endcomponent
