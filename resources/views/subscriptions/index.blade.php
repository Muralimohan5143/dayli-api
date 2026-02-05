<x-layouts.app>
  <div class="max-w-6xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-semibold mb-4">Manage Subscriptions</h1>

    @php
      $uid = auth()->id();

      $productTypes = \Illuminate\Support\Facades\DB::table('user_active_product_subscriptions')
          ->where('user_id', $uid)->distinct()->pluck('product_type')->toArray();

      $serviceTypes = \Illuminate\Support\Facades\DB::table('user_active_service_subscriptions')
          ->where('user_id', $uid)->distinct()->pluck('service_type')->toArray();

      $cards = collect($productTypes)->map(fn($t) => [
          'kind'  => 'product',
          'label' => $t,
          'slug'  => \Illuminate\Support\Str::slug($t),
          'href'  => route('subs.products.show', ['type' => \Illuminate\Support\Str::slug($t)]),
          'icon'  => '🧺',
      ])->merge(
          collect($serviceTypes)->map(fn($t) => [
              'kind'  => 'service',
              'label' => $t,
              'slug'  => \Illuminate\Support\Str::slug($t),
              'href'  => route('subs.services.show', ['type' => \Illuminate\Support\Str::slug($t)]),
              'icon'  => '🛠️',
          ])
      )->values();
    @endphp

    @if($cards->isEmpty())
      <div class="p-6 border rounded-lg bg-gray-50">No active subscriptions yet.</div>
    @else
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($cards as $c)
          <a href="{{ $c['href'] }}" class="group block border rounded-xl p-5 hover:shadow transition">
            <div class="text-3xl mb-3">{{ $c['icon'] }}</div>
            <div class="text-sm uppercase tracking-wide text-gray-500">{{ ucfirst($c['kind']) }}</div>
            <div class="text-lg font-medium">{{ $c['label'] }}</div>
            <div class="mt-2 text-sm text-blue-600 group-hover:underline">Manage →</div>
          </a>
        @endforeach
      </div>
    @endif
  </div>
</x-layouts.app>
