<div x-data="{ saving:false }" class="container-fluid py-3">
    <div class="card shadow-sm border-0">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div>
                <h5 class="mb-0">My Work — {{ $type?->name ?? 'Subscription' }}</h5>
                <small class="text-muted">Post today’s actuals from your contract items</small>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <input type="date" class="form-control" style="max-width: 180px;" wire:model.defer="deliverDate">
                <input type="text" class="form-control" placeholder="Search product / variant" style="max-width: 260px;" wire:model.debounce.400ms="search">
                <button class="btn btn-primary" :disabled="saving" @click="saving=true" wire:click="save" @done.window="saving=false">
                    <span class="spinner-border spinner-border-sm me-1" x-show="saving"></span>
                    Save Actuals
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-items-center mb-0">
                    <thead>
                        <tr>
                            <th class="text-secondary text-xs font-weight-bolder opacity-7">Product</th>
                            <th class="text-secondary text-xs font-weight-bolder opacity-7">Variant</th>
                            <th class="text-secondary text-xs font-weight-bolder opacity-7">Unit</th>
                            <th class="text-secondary text-xs font-weight-bolder opacity-7">Planned</th>
                            <th class="text-secondary text-xs font-weight-bolder opacity-7">Actual ({{ $deliverDate }})</th>
                            <th class="text-secondary text-xs font-weight-bolder opacity-7">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                        <tr>
                            <td class="text-sm">{{ $row->product_name ?? '—' }}</td>
                            <td class="text-sm">{{ $row->variant_name ?? '—' }}</td>
                            <td class="text-sm">{{ $row->unit }}</td>
                            <td class="text-sm">{{ rtrim(rtrim(number_format($row->qty,2,'.',''), '0'),'.') }}</td>
                            <td class="text-sm" style="max-width: 140px;">
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model.lazy="actuals.{{ $row->id }}">
                                @error('actuals.'.$row->id)
                                <div class="text-danger small">{{ $message }}</div>
                                @enderror
                            </td>
                            <td class="text-sm">
                                <input type="text" class="form-control form-control-sm" placeholder="optional" wire:model.lazy="notes.{{ $row->id }}">
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No contract items found for this subscription type.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-3 pb-3">
                {{ $rows->links() }}
            </div>
        </div>
    </div>
</div>

<script>
    window.addEventListener('toast', e => {
        const evt = new CustomEvent('done');
        window.dispatchEvent(evt);
    });
</script>