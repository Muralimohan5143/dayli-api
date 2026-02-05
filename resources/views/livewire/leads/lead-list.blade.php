<div>
    <div class="mb-3">
        <input type="text" class="form-control" placeholder="Search by name or phone..." wire:model.debounce.300ms="search">
    </div>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>First Name</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Zone</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($leads as $lead)
                <tr>
                    <td>{{ $lead->first_name }}</td>
                    <td>{{ $lead->phone }}</td>
                    <td>{{ ucfirst($lead->status) }}</td>
                    <td>{{ $lead->zone }}</td>
                    <td>{{ $lead->created_at->format('d M Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{ $leads->links() }}
</div>
