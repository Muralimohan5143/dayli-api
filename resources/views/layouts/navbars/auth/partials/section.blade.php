{{-- resources/views/layouts/navbars/auth/partials/section.blade.php --}}
@props([
  'title' => '',
  'key' => '',
  'defaultOpen' => true,
])

<li class="nav-item">
  <div
    x-data="{
      k: '{{ $key }}',
      isOpen: (localStorage.getItem('{{ $key }}') !== null)
                ? JSON.parse(localStorage.getItem('{{ $key }}'))
                : {{ $defaultOpen ? 'true' : 'false' }},
      toggle(){
        this.isOpen = !this.isOpen;
        localStorage.setItem(this.k, JSON.stringify(this.isOpen));
      }
    }"
    class="w-100"
  >
    {{-- Header --}}
    <button type="button"
            @click="toggle()"
            :aria-expanded="isOpen.toString()"
            class="sidebar-section-header w-100 d-flex align-items-center justify-content-between">
      <span class="uppercase tracking-wide text-gray-800 text-sm font-bold">
        {{ $title }}
      </span>
      {{-- SVG chevron rotates via inline style so it can't be overridden --}}
      <svg class="caret" xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
           viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
           :style="{ transform: isOpen ? 'rotate(90deg)' : 'rotate(0deg)' }">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
      </svg>
    </button>

    {{-- Body (slot content) --}}
    <ul class="list-unstyled sidebar-section-content ps-2 mb-0"
        x-show="isOpen"
        x-transition.duration.150ms
        x-cloak
        style="overflow:hidden;">
      {{ $slot }}
    </ul>
  </div>
</li>
