<div class="container py-4">
    <h2 class="text-xl font-semibold mb-4">Delete Delivery Record #{{ $deliveryId }}</h2>

    @if(!$confirming)
        <p>Are you sure you want to delete this delivery record?</p>
        <div class="mt-4">
            <button wire:click="confirmDelete" class="btn btn-danger">Yes, Delete</button>
            <a href="{{ route('sub-delivery-actuals.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    @else
        <p class="text-red-600">This delivery will be <strong>soft deleted</strong> and retained in the database for record-keeping.</p>
        <div class="mt-4">
            <button wire:click="delete" class="btn btn-danger">Confirm Delete</button>
            <a href="{{ route('sub-delivery-actuals.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    @endif
</div>
