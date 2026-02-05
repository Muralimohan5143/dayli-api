<div class="container py-4">
    <h2 class="text-xl font-semibold mb-4">Delete Sub Change Request #{{ $requestId }}</h2>

    @if(!$confirming)
        <p>Are you sure you want to delete this request?</p>
        <div class="mt-4">
            <button wire:click="confirmDelete" class="btn btn-danger">Yes, Delete</button>
            <a href="{{ route('sub-change-requests.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    @else
        <p class="text-red-600">This action is permanent and cannot be undone. Confirm deletion?</p>
        <div class="mt-4">
            <button wire:click="delete" class="btn btn-danger">Confirm Delete</button>
            <a href="{{ route('sub-change-requests.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    @endif
</div>
