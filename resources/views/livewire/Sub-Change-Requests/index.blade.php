<div class="container py-4">
    <h2 class="text-xl font-semibold mb-4">Sub Change Requests</h2>

    <div class="flex justify-between mb-4">
        <input wire:model.debounce.500ms="search" type="text" placeholder="Search by customer or status..." class="form-input px-4 py-2 rounded border" />
        <a href="{{ route('sub-change-requests.create') }}" class="btn btn-primary">+ Create New</a>
    </div>

    <table class="table-auto w-full text-left border border-gray-200">
        <thead>
            <tr class="bg-gray-100">
                <th class="px-4 py-2">ID</th>
                <th class="px-4 py-2">Customer</th>
                <th class="px-4 py-2">Product</th>
                <th class="px-4 py-2">Qty</th>
                <th class="px-4 py-2">Frequency</th>
                <th class="px-4 py-2">Status</th>
                <th class="px-4 py-2">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($requests as $req)
            <tr>
                <td class="px-4 py-2">{{ $req->id }}</td>
                <td class="px-4 py-2">{{ $req->customer->name ?? '-' }}</td>
                <td class="px-4 py-2">
                    {{ $req->first_product_title ?? '-' }}
                </td>
                <td class="px-4 py-2">
                    {{ rtrim(rtrim(number_format($req->items_qty_sum ?? 0, 2, '.', ''), '0'), '.') }}
                </td>
                
                <td class="px-4 py-2">{{ $req->frequency_type }}</td>
                <td class="px-4 py-2">{{ $req->status }}</td>
                <td class="px-4 py-2">
                    <a href="{{ route('sub-change-requests.edit', $req->id) }}" class="text-blue-500 hover:underline">Edit</a>
                    
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-4 py-4 text-center">No records found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">
        {{ $requests->links() }}
    </div>
</div>