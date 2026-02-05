{{-- resources/views/components/layouts/blank.blade.php --}}
<!doctype html><html><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  @livewireStyles
</head><body class="bg-light">
  {{ $slot }}
  @livewireScripts
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body></html>
