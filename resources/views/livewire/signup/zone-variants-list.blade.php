{{-- resources/views/livewire/signup/zone-variants-list.blade.php --}}
<div>
    @php $switchId = uniqid('z-only-'); @endphp

    <div class="card shadow-sm border-0">
        {{-- Header controls --}}
        <div class="card-header bg-white d-flex align-items-center justify-content-between py-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="input-group input-group-sm" style="min-width: 320px;">
                    <span class="input-group-text">Search (product)</span>
                    <input
                        type="search"
                        class="form-control"
                        placeholder="Type to filter…"
                        wire:model.live.debounce.300ms="q"
                        autocomplete="off">
                </div>
            </div>
            <!-- 
            <div class="form-check form-switch">
                <input id="{{ $switchId }}" class="form-check-input" type="checkbox"
                    wire:click="toggleOnlyActive" @checked($onlyActive)>
                <label class="form-check-label small" for="{{ $switchId }}">Only active in zone</label>
            </div> -->
        </div>

        {{-- Table --}}
        <div class="zone-variants-wrapper table-responsive">
            <table class="table table-bordered table-striped align-middle w-100">
                <thead class="table-light">
                    <tr>
                        {{-- no checkbox in header --}}
                        <th>Product</th>
                        <th>Variant</th>

                        {{-- SKU → Frequency (as requested) --}}
                        <th>Frequency</th>

                        <th>MRP</th>

                        {{-- Mode column REMOVED as requested --}}
                        {{-- <th>Mode</th> --}}

                        <th>Discount %</th>
                        <th>Discount Amt</th>
                        <th>Cost Price</th>

                        {{-- After Cost Price: Start Date & End Date --}}
                        <th>Start Date</th>
                        <th>End Date</th>

                        {{-- Active In Zone REMOVED as requested --}}
                        {{-- <th>Active In Zone</th> --}}
                    </tr>
                </thead>

                <tbody>
                    @forelse ($page as $row)
                    @php
                    $vid = (int) $row->variant_id;
                    $pid = (int) $row->product_id;
                    $cbId = 'sel_'.$vid; // unique ID for the checkbox + label
                    @endphp
                    <tr wire:key="row-{{ $vid }}">

                        {{-- Product (checkbox INSIDE the Product cell) --}}
                        <td class="fw-semibold">
                            <div class="form-check m-0">
                                <input type="checkbox"
                                    id="sel_{{ $vid }}"
                                    class="form-check-input"
                                    wire:model="rows.{{ $vid }}.selected"
                                    wire:change="pushSelectionUp">
                                <label class="form-check-label" for="sel_{{ $vid }}">
                                    {{ $row->product_title }}
                                </label>
                            </div>

                            {{-- keep product_id in Livewire state --}}
                            <input type="hidden" wire:model="rows.{{ $vid }}.product_id" value="{{ $pid }}">
                        </td>

                        {{-- Variant --}}
                        <td>{{ $rows[$vid]['title'] ?? $row->variant_title }}</td>

                        {{-- Frequency (replaces SKU) --}}
                        <td>
                            <select class="form-select form-select-sm"
                                wire:model.live="rows.{{ $vid }}.frequency_type"
                                wire:change="pushSelectionUp">
                                <option value="daily">Daily</option>
                                <option value="alternate_days">Alternate days</option>
                                <option value="weekdays">Weekdays</option>
                                <option value="weekends">Weekends</option>
                                <option value="sat">Saturday</option>
                                <option value="sun">Sunday</option>
                                <option value="custom">Custom</option>
                                <option value="on_demand">On demand</option>
                            </select>
                        </td>
                        {{-- MRP --}}
                        <td>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">₹</span>
                                <input type="number" min="0" step="0.01" class="form-control"
                                    wire:model.lazy="rows.{{ $vid }}.mrp"
                                    wire:change="updateMrp({{ $vid }})">
                            </div>
                        </td>

                        {{-- Mode column REMOVED --}}
                        {{--
                            <td>
                                ... old mode buttons ...
                            </td>
                            --}}

                        {{-- Discount % --}}
                        <td>
                            <div class="input-group input-group-sm">
                                <input type="number" min="0" max="100" step="0.01"
                                    class="form-control"
                                    wire:model.lazy="rows.{{ $vid }}.percent"
                                    wire:change="updatePercent({{ $vid }})"
                                    @disabled(!($rows[$vid]['selected'] ?? false))>
                                <span class="input-group-text">%</span>
                            </div>
                        </td>

                        {{-- Discount Amt --}}
                        <td>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">₹</span>
                                <input type="number" min="0" step="0.01"
                                    class="form-control"
                                    wire:model.lazy="rows.{{ $vid }}.amount"
                                    wire:change="updateAmount({{ $vid }})"
                                    @disabled(!($rows[$vid]['selected'] ?? false))>
                            </div>
                        </td>

                        {{-- Cost price (readonly) --}}
                        <td>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control"
                                    value="{{ $rows[$vid]['cost'] ?? '' }}" readonly>
                            </div>
                        </td>

                        {{-- Start Date (AFTER Cost Price) --}}
                        <td>
                            <input type="date" class="form-control form-control-sm"
                                wire:model.lazy="rows.{{ $vid }}.start_date"
                                wire:change="pushSelectionUp">
                        </td>

                        {{-- End Date --}}
                        <td>
                            <input type="date" class="form-control form-control-sm"
                                wire:model.lazy="rows.{{ $vid }}.end_date"
                                wire:change="pushSelectionUp">
                        </td>

                        {{-- Active In Zone REMOVED --}}
                        {{-- <td>{{ ($rows[$vid]['active_in_zone'] ?? false) ? 'Yes' : 'No' }}</td> --}}
                    </tr>
                    @empty
                    <tr>
                        {{-- column count remains 9 after refactor --}}
                        <td colspan="9" class="text-center text-muted py-4">
                            No variants found for this subtype in the selected zone.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($page->hasPages())
        <div class="card-footer bg-white pb-2">
            {{ $page->onEachSide(1)->links() }}
        </div>
        @endif
    </div>

    {{-- Scoped CSS --}}
    <style>
        .zone-variants-wrapper {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
        }

        .zone-variants-table {
            width: 1400px;
            margin: 0 auto;
        }

        .zone-variants-table th,
        .zone-variants-table td {
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }
    </style>
</div>