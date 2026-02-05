<h1 class="text-xl font-bold">Service: {{ $typeName }}</h1>
<ul class="mt-3 space-y-2">
  @forelse($items as $it)
    <li class="p-3 border rounded">{{ $it->service_title }} — qty: {{ $it->qty }} {{ $it->unit }}</li>
  @empty
    <li>No active items.</li>
  @endforelse
</ul>
