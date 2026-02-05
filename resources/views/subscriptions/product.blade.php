<h1 class="text-xl font-bold">Product: {{ $typeName }}</h1>
<ul class="mt-3 space-y-2">
  @forelse($items as $it)
    <li class="p-3 border rounded">{{ $it->product_title }} — qty: {{ $it->qty }} {{ $it->unit }}</li>
  @empty
    <li>No active items.</li>
  @endforelse
</ul>
