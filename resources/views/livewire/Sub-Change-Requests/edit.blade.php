<div>
    @if($open)
    <div
        wire:key="scr-edit-overlay"
        wire:ignore.self
        x-data
        x-cloak
        x-transition.opacity
        @keydown.escape.window="$wire.close()"
        @click.self="$wire.close()"
        style="position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:99999;">

        {{-- IMPORTANT: stop clicks from reaching the overlay --}}
        <form
            wire:submit.prevent="save"
            @click.stop
            class="modal-dialog"
            style="max-width:640px; margin:8vh auto; background:#fff; border-radius:1rem; overflow:hidden;">

            <div class="p-4 border-bottom">
                <h5 class="fw-bold mb-1">Edit Subscription</h5>
                <div class="text-muted">Change qty, cadence and dates.</div>
            </div>

            <div class="p-4">
                <div class="mb-3">
                    <label class="form-label" for="qty">Qty</label>
                    <input id="qty" type="number" step="0.01" min="0"
                        class="form-control" wire:model.defer="qty">
                    @error('qty') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label" for="freq">Frequency Type</label>
                    <select id="freq" class="form-select" wire:model.defer="frequency_type">
                        <option value="daily">Daily</option>
                        <option value="alternate_days">Alternate Days</option>
                        <option value="weekdays">Weekdays</option>
                        <option value="weekends">Weekends</option>
                        <option value="sat">Saturday Only</option>
                        <option value="sun">Sunday Only</option>
                        <option value="custom">Custom</option>
                        <option value="on_demand">On Demand</option>
                    </select>
                    @error('frequency_type') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="start_date">Start Date</label>
                        <input id="start_date" type="date" class="form-control" wire:model.defer="start_date">
                        @error('start_date') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="end_date">End Date</label>
                        <input id="end_date" type="date" class="form-control" wire:model.defer="end_date">
                        @error('end_date') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="p-3 border-top d-flex justify-content-end gap-2 bg-light">
                <button type="button" class="btn btn-light" wire:click="close">Cancel</button>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Save</span>
                    <span wire:loading>Saving…</span>
                </button>
            </div>
        </form>
    </div>
    @endif
</div>