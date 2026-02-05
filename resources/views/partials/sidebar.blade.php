<nav class="space-y-2">
  <a href="{{ route('subs.index') }}" class="font-semibold">Manage Subscriptions</a>

  @if(!empty($sidebarSubs['products']))
    <div class="ml-3 text-sm text-gray-600 mt-2">Products</div>
    <ul class="ml-4 space-y-1">
      @foreach($sidebarSubs['products'] as $pt)
        <li>
          <a class="hover:underline" href="{{ route('subs.products.show', ['type' => $pt['slug']]) }}">
            {{ $pt['label'] }}
          </a>
        </li>
      @endforeach
    </ul>
  @endif

  @if(!empty($sidebarSubs['services']))
    <div class="ml-3 text-sm text-gray-600 mt-3">Services</div>
    <ul class="ml-4 space-y-1">
      @foreach($sidebarSubs['services'] as $st)
        <li>
          <a class="hover:underline" href="{{ route('subs.services.show', ['type' => $st['slug']]) }}">
            {{ $st['label'] }}
          </a>
        </li>
      @endforeach
    </ul>
  @endif
</nav>
