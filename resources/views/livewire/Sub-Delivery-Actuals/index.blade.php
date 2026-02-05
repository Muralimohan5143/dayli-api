<div class="container py-4">
    <h2 class="text-xl font-semibold mb-4">Sub Delivery Actuals</h2>

    <div class="flex justify-between mb-4">
        <input wire:model.debounce.500ms="search" type="text" placeholder="Search by customer or status..." class="form-input px-4 py-2 rounded border" />
        <a href="{{ route('sub-delivery-actuals.create') }}" class="btn btn-primary">+ Log New</a>
    </div>

    <table class="table-auto w-full text-left border border-gray-200">
        <thead>
            <tr class="bg-gray-100">
                <th class="px-4 py-2">ID</th>
                <th class="px-4 py-2">Customer</th>
                <th class="px-4 py-2">Product</th>
                <th class="px-4 py-2">Qty</th>
                <th class="px-4 py-2">Status</th>
                <th class="px-4 py-2">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($deliveries as $d)
            <tr>
                <td class="px-4 py-2">{{ $d->id }}</td>
                <td class="px-4 py-2">{{ $d->customer->name ?? '-' }}</td>
                <td class="px-4 py-2">{{ $d->product->title ?? '-' }}</td>
                <td class="px-4 py-2">{{ $d->product_count }}</td>
                <td class="px-4 py-2">{{ ucfirst(str_replace('_', ' ', $d->status)) }}</td>
                <td class="px-4 py-2">
                    <a href="{{ route('sub-delivery-actuals.edit', $d->id) }}" class="text-blue-500 hover:underline">Edit</a>
                    |
                    <a href="{{ route('sub-delivery-actuals.delete', $d->id) }}" class="text-red-500 hover:underline">Delete</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-4 py-4 text-center">No deliveries found.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">
        {{ $deliveries->links() }}
    </div>
</div>
