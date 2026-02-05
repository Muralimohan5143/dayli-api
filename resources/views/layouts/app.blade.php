<x-layouts.base>

  {{-- global styles --}}
  <style>
    .cr-page-surface {
      background-color: #f5f6ff;
      min-height: 100vh;
    }

    .cr-shell {
      display: flex;
      min-height: 100vh;
      width: 100%;
    }

    .cr-sidebar {
      width: 270px;
      background-color: #2d3036;
      /* dark panel */
      color: #fff;
      display: flex;
      flex-direction: column;
      border-right: 1px solid rgba(0, 0, 0, 0.6);
      box-shadow: 4px 0 16px rgba(0, 0, 0, 0.55);
    }

    .cr-sidebar .cr-side-section-label {
      font-size: .75rem;
      font-weight: 600;
      color: #9ca3af;
      text-transform: uppercase;
      letter-spacing: .03em;
      margin: 1rem 1rem .5rem;
    }

    .cr-sidebar .cr-side-link {
      display: flex;
      align-items: center;
      gap: .5rem;
      background-color: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.08);
      color: #fff;
      border-radius: .5rem;
      padding: .75rem .9rem;
      font-size: .9rem;
      margin: 0 1rem .5rem;
      text-decoration: none;
    }

    .cr-sidebar .cr-side-link.active {
      background-color: rgba(255, 255, 255, 0.12);
      border-color: rgba(255, 255, 255, 0.22);
    }

    .cr-mainpane {
      flex: 1;
      display: flex;
      flex-direction: column;
      background-color: #f5f6ff;
    }

    .cr-card {
      background-color: #ffffff;
      border-radius: 0.75rem;
      border: 1px solid rgba(0, 0, 0, 0.03);
      box-shadow:
        0 12px 24px -4px rgba(16, 24, 40, 0.08),
        0 2px 4px rgba(16, 24, 40, 0.04);
    }

    .cr-card-section-soft {
      background-color: #eef0ff;
      border-radius: 0.75rem;
    }

    .cr-label {
      font-size: .75rem;
      font-weight: 600;
      color: #4b5563;
      text-transform: uppercase;
      letter-spacing: .03em;
    }
  </style>

  @auth
  <div class="cr-shell">

    {{-- LEFT SIDEBAR --}}
    <aside class="cr-sidebar">
      {{-- You can either fully replace the include with custom markup,
                     OR wrap your existing include so it inherits the dark styles.
                     I'll wrap it. --}}
      <div class="flex-grow-1 overflow-auto">
        @include('layouts.navbars.auth.sidebar-dynamic')
      </div>
    </aside>

    {{-- RIGHT MAIN PANE --}}
    <div class="cr-mainpane">
      <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">

        {{-- top nav (your breadcrumb / user etc.) --}}
        @includeWhen(view()->exists('layouts.navbars.auth.topnav'), 'layouts.navbars.auth.topnav')

        {{-- page body slot --}}
        @isset($slot)
        {{ $slot }}
        @else
        @yield('content')
        @endisset

        {{-- footer --}}
        @includeWhen(view()->exists('layouts.footers.auth.footer'), 'layouts.footers.auth.footer')
      </main>
    </div>

  </div>
  @else
  {{ $slot }}
  @endauth
</x-layouts.base>