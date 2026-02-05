{{-- resources/views/layouts/app.blade.php --}}
<x-layouts.base>
  @auth
    @php $user = auth()->user(); @endphp

    <div class="g-sidenav-show bg-gray-100">
      
      {{-- ======================== SIDEBAR BY ROLE ========================= --}}
      @if ($user->hasRole('admin'))
          @include('layouts.navbars.auth.sidebar-dynamic')
        @include('components.plugins.fixed-plugin')
      @elseif ($user->hasRole('zone-manager'))
        @include('layouts.navbars.auth.sidebar-zone-manager')
      @elseif ($user->hasRole('workman-delivery-boy-milk'))
        @include('layouts.navbars.auth.sidebar-workman-delivery-boy-milk')
      @elseif ($user->hasRole('vendor-milk'))
        @include('layouts.navbars.auth.sidebar-vendor-milk')
      @elseif ($user->hasRole('customer'))
        @include('layouts.navbars.auth.sidebar-customer')
      @else
        <x-alert type="warning" message="Sidebar not configured for your role." />
      @endif

      {{-- ======================== MAIN CONTENT WRAPPER ========================= --}}
      <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
         @include('layouts.navbars.auth.topnav')  

        {{ $slot }}

        @include('layouts.footers.auth')
      </main>

    </div>
  @else
    {{ $slot }}
  @endauth
</x-layouts.base>
