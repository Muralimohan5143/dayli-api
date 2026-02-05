{{-- resources/views/livewire/sub-change-requests/grouped-by-type.blade.php --}}

<div
    class="cr-page-surface"
    x-data="{
        openKey: null,
        toggle(k) {
            this.openKey = (this.openKey === k) ? null : k;
        }
    }"
    x-cloak
    style="padding:2rem 1rem;background:#f8f9ff;min-height:100vh;">

    {{-- PAGE TITLE --}}
    <div class="mb-3 text-center" style="max-width:980px;margin:0 auto;">
        <h2 class="fw-semibold mb-1"
            style="font-size:1.6rem;color:#1f3b57;">
            Sub Change Requests
        </h2>
        <!-- <button type="button"
            class="btn btn-secondary btn-sm mt-2"
            onclick="@this.call('testClick')">
            TEST CLICK
        </button> -->
        {{-- debug info --}}
        <div style="color:#dc2626;font-size:12px;line-height:1.4;margin-top:.5rem;">
            debugHit: {{ $debugHit }} <br>
            showCreateModal: {{ $showCreateModal ? 'true' : 'false' }} <br>
            createTypeName: {{ $createTypeName }}
        </div>
    </div>

    {{-- EMPTY STATE --}}
    @if($groups->isEmpty())
    <div class="alert alert-info text-center mx-auto"
        style="max-width:560px;border-radius:.75rem;">
        No data yet. Create a contract to get started.
    </div>
    @else

    {{-- CARD CONTAINER --}}
    <div class="cr-card mx-auto p-0"
        style="max-width:560px;border-radius:.75rem;overflow:hidden;">

        <style>
            .dayli-acc .acc-head {
                cursor: pointer;
                user-select: none;
                transition: all .2s ease;
                border: 0;
                width: 100%;
                text-align: left;
            }

            .dayli-acc .acc-item:nth-child(odd) .acc-head {
                background: #fff7ed;
            }

            .dayli-acc .acc-item:nth-child(even) .acc-head {
                background: #eff6ff;
            }

            .dayli-acc .acc-head:hover {
                filter: brightness(.98);
            }

            .dayli-acc .chev {
                transition: transform .25s ease;
            }

            .dayli-acc .acc-body {
                background: #ffffff;
            }

            .badge-chip {
                background: #f3f4f6;
                border: 1px solid #e5e7eb;
                border-radius: 9999px;
                padding: .1rem .5rem;
                font-size: .72rem;
                color: #6b7280;
                font-weight: 500;
                line-height: 1rem;
            }

            .meta-line {
                font-size: .78rem;
                color: #64748b;
            }
        </style>

        <div class="dayli-acc">
            @foreach($groups as $idx => $group)
            @php
            $panelKey = 'g'.$idx;
            $uid = 'acc_'.$panelKey;
            $typeName = $group['type_name'];
            $totalQty = $group['total_qty'];
            $products = $group['products']; // array/collection of product rows

            // ✅ latest CR id across ALL products in this group
            $fromId = collect($products)
            ->flatMap(fn($p) => collect($p['cr_ids'] ?? []))
            ->sortDesc()
            ->first();

            $totalQtyDisplay = rtrim(rtrim(number_format((float)$totalQty, 2, '.', ''), '0'), '.');
            @endphp

            <div class="acc-item border-bottom" style="border-color:#e5e7eb;">
                {{-- ACCORDION HEADER --}}
                <button
                    type="button"
                    class="d-flex align-items-center justify-content-between acc-head px-3 py-3"
                    @click="openKey = (openKey === '{{ $panelKey }}' ? null : '{{ $panelKey }}')"
                    :aria-expanded="openKey === '{{ $panelKey }}' ? 'true' : 'false'"
                    :aria-controls="'{{ $uid }}'">
                    <div class="d-flex flex-column align-items-start">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-semibold" style="font-size:.95rem;color:#111827;">
                                {{ $typeName }}
                            </span>
                            <span class="badge-chip">
                                {{ $products->count() }} product(s)
                            </span>
                        </div>
                        <div class="meta-line">
                            Total Qty: {{ $totalQtyDisplay }}
                        </div>
                    </div>

                    <svg
                        class="chev"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        :style="openKey === '{{ $panelKey }}'
                                            ? 'transform:rotate(180deg);transition:.2s;'
                                            : 'transition:.2s;'">
                        <path d="M6 9l6 6 6-6" />
                    </svg>
                </button>

                {{-- ACCORDION BODY --}}
                <div
                    id="{{ $uid }}"
                    class="acc-body px-3 pb-3"
                    x-show="openKey === '{{ $panelKey }}'"
                    x-collapse>

                    {{-- toolbar: search + create --}}
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"
                        style="
                                    border:1px solid #e5e7eb;
                                    border-radius:.75rem;
                                    background:#fff;
                                    padding:.75rem 1rem;
                                ">

                        {{-- search --}}
                        <div class="flex-grow-1" style="min-width:200px;max-width:400px;">
                            <input
                                wire:model.debounce.400ms="search"
                                type="text"
                                placeholder="Search {{ $typeName }} products…"
                                class="form-control"
                                style="
                                            border-radius:9999px;
                                            font-size:.85rem;
                                            line-height:1.2rem;
                                            padding:.55rem .9rem;
                                            border:1px solid #cfd3dc;
                                        ">
                        </div>

                        {{-- CREATE NEW --}}
                        <div class="shrink-0">
                            <button
                                type="button"
                                wire:click="openCreateModal({{ $group['type_id'] }}, '{{ addslashes($typeName) }}', {{ $fromId ?? 'null' }})"
                                class="btn btn-outline-dark"
                                style="
                                            border-radius:9999px;
                                            font-size:.8rem;
                                            line-height:1.1rem;
                                            padding:.6rem .9rem;
                                            text-align:center;
                                            min-width:6rem;
                                        ">
                                + CREATE NEW
                            </button>
                        </div>



                        {{-- right side of the toolbar, after + CREATE NEW --}}
                        <div class="position-relative"
                            x-data="{open:false}"
                            @click.outside="open=false">
                            <button type="button"
                                class="btn btn-light"
                                style="border-radius:999px;width:40px;height:40px;border:1px solid #e5e7eb;"
                                @click="open = !open"
                                aria-label="More actions">
                                {{-- 3 vertical dots --}}
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                    <circle cx="12" cy="5" r="1.8" fill="#111827"></circle>
                                    <circle cx="12" cy="12" r="1.8" fill="#111827"></circle>
                                    <circle cx="12" cy="19" r="1.8" fill="#111827"></circle>
                                </svg>
                            </button>

                            {{-- DROPDOWN --}}
                            <div x-show="open" x-transition
                                class="dropdown-menu show"
                                style="position:absolute;right:0;top:44px;min-width:220px;border:1px solid #e5e7eb;border-radius:.75rem;padding:.25rem;background:#fff;box-shadow:0 10px 30px rgba(0,0,0,.08);">
                                <button type="button"
                                    class="dropdown-item d-flex align-items-center gap-2"
                                    style="font-weight:600;color:#b91c1c;"
                                    @click="
  open=false;
  @this.call('askDeactivateType', {{ (int) $group['type_id'] }}, @js($group['type_name']))
">
                                    {{-- small warning icon --}}
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                        <path d="M10.3 3.9 2.6 17.3A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.7L13.7 3.9a2 2 0 0 0-3.4 0Z" stroke="#b91c1c" stroke-width="1.4" />
                                        <path d="M12 9v4" stroke="#b91c1c" stroke-width="1.8" stroke-linecap="round" />
                                        <circle cx="12" cy="16.5" r="1" fill="#b91c1c" />
                                    </svg>
                                    Deactivate “{{ $group['type_name'] }}”
                                </button>
                            </div>
                        </div>

                    </div>

                    {{-- product table --}}
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" style="font-size:.8rem;">
                            <thead class="text-muted" style="font-size:.7rem;">
                                <tr>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Unit</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($products as $p)
                                @php
                                $editCrId = $p['cr_ids']->sortDesc()->first();
                                $qtyDisplay = rtrim(
                                rtrim(number_format((float)$p['qty_sum'], 2, '.', ''), '0'),
                                '.'
                                );
                                @endphp

                                <tr>
                                    <td class="fw-medium" style="color:#111827;">
                                        {{ $p['title'] }}
                                    </td>
                                    <td>{{ $qtyDisplay }}</td>
                                    <td>{{ $p['unit'] }}</td>
                                    <td>
                                        {{-- inside <td> where EDIT button is rendered --}}
                                        @if($editCrId)
                                        {{-- inside your products loop --}}
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary btn-sm"
                                            wire:click="openEdit({{ $p['product_id'] }}, @js($p['cr_ids']), @js($p['title']), {{ (float)($p['qty_sum'] ?? 1) }}, @js($p['unit'] ?? 'pcs'))">
                                            EDIT
                                        </button>
                                        @else
                                        <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
            @endforeach
        </div>


    </div> {{-- /.cr-card --}}

    @endif {{-- groups not empty --}}



    {{-- ✅ Deactivate confirm modal (event-safe) --}}
    @if ($showDeactivateConfirm)
    <div wire:key="deactivate-confirm-modal"
        x-data
        x-cloak
        x-on:keydown.escape.window="$wire.cancelDeactivate()"
        style="position:fixed; inset:0; z-index:11000;">

        {{-- backdrop (clicking it closes) --}}
        <div style="position:absolute; inset:0; background:rgba(0,0,0,.35);"
            x-on:click="$wire.cancelDeactivate()"></div>

        {{-- dialog --}}
        <div style="position:relative; max-width:520px; margin:10vh auto; background:#fff; border-radius:1rem; overflow:hidden;"
            x-on:click.stop>
            <div class="p-4">
                <h5 class="mb-2 fw-bold">Deactivate “{{ $targetTypeName }}”?</h5>
                <p class="text-muted mb-0">
                    This hides this subscription type for new orders. You can re-activate later.
                </p>
            </div>

            <div class="p-3 d-flex justify-content-end gap-2" style="background:#f9fafb;">
                <button type="button"
                    class="btn btn-light"
                    x-on:click.stop
                    wire:click="cancelDeactivate">
                    CANCEL
                </button>

                <button type="button"
                    class="btn btn-danger"
                    x-on:click.stop
                    wire:click.prevent="confirmDeactivate"
                    wire:loading.attr="disabled">
                    <span wire:loading.remove>DEACTIVATE</span>
                    <span wire:loading>Working…</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- EDIT MODAL (safe click handling) --}}
    @if ($showEditModal)
    <div wire:key="edit-item-modal"
        x-data
        x-cloak
        x-on:keydown.escape.window="$wire.cancelEdit()"
        style="position:fixed; inset:0; z-index:11010;">

        {{-- 1) Backdrop: ONLY this closes on click --}}
        <div style="position:absolute; inset:0; background:rgba(0,0,0,.35);"
            x-on:click="$wire.cancelEdit()"></div>

        {{-- 2) Dialog: STOP all clicks so they never hit the backdrop --}}
        <div style="position:relative; max-width:640px; margin:10vh auto; background:#fff; border-radius:1rem; overflow:hidden;"
            x-on:click.stop
            x-on:click.outside="$wire.cancelEdit()">

            <div class="p-4">
                <h5 class="fw-bold mb-3">Edit: {{ $editProductTitle }}</h5>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Qty</label>
                        <input type="number" min="0" step="0.01" class="form-control"
                            x-on:click.stop
                            wire:model.defer="editQty">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Unit</label>
                        <input type="text" class="form-control"
                            x-on:click.stop
                            wire:model.defer="editUnit">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Frequency</label>
                        <select class="form-select"
                            x-on:click.stop
                            wire:model.defer="editFrequencyType">
                            <option value="daily">Daily</option>
                            <option value="alternate_days">Alternate Days</option>
                            <option value="weekdays">Weekdays</option>
                            <option value="weekends">Weekends</option>
                            <option value="sat">Saturdays</option>
                            <option value="sun">Sundays</option>
                            <option value="custom">Custom</option>
                            <option value="on_demand">On Demand</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mt-3"
                    x-data="{
       today: (new Date()).toISOString().slice(0,10),   // 'YYYY-MM-DD'
       start: @entangle('editStartDate').defer,
       end:   @entangle('editEndDate').defer
     }">

                    {{-- Start Date (>= today) --}}
                    <div class="col-md-6">
                        <label class="form-label">Start Date</label>
                        <input
                            type="date"
                            class="form-control"
                            x-on:click.stop
                            x-model="start"
                            :min="today"
                            wire:model.defer="editStartDate">
                    </div>

                    {{-- End Date (>= start, else >= today) --}}
                    <div class="col-md-6">
                        <label class="form-label">End Date</label>
                        <input
                            type="date"
                            class="form-control"
                            x-on:click.stop
                            x-model="end"
                            :min="(start || today)"
                            wire:model.defer="editEndDate">
                    </div>
                </div>

            </div>



            <div class="p-3 d-flex justify-content-end gap-2 bg-light">
                <button type="button" class="btn btn-light"
                    x-on:click.stop
                    wire:click="cancelEdit">CANCEL</button>

                <button type="button" class="btn btn-primary"
                    x-on:click.stop
                    wire:click.prevent="saveEdit"
                    wire:loading.attr="disabled">
                    <span wire:loading.remove>SAVE</span>
                    <span wire:loading>SAVING…</span>
                </button>
            </div>
        </div>
    </div>
    @endif


    {{-- MODAL OVERLAY with child component --}}
    {{-- MODAL OVERLAY --}}
    @if($showCreateModal)
    <div
        tabindex="-1"
        style="
         --sidebar-w: 260px;           /* left sidebar width */
    --extra-shift: 80px;         /* 👈 how much more to push right */
        position:fixed;
        inset:0;
        background:rgba(0,0,0,.35);
        display:flex;
        align-items:flex-start; 
        justify-content:center;
        padding:4rem 24px 24px calc(var(--sidebar-w) + var(--extra-shift) + 24px);
        z-index:11000; /* was 0 */
    ">
        <div
            class="cr-card"
            style="
            width:100%;
             max-width:70vw;      
                /* width:1600px;  */
            max-height:80vh;
            overflow:auto;
            border-radius:.75rem;
            background:#ffffff;
            box-shadow:
                0 24px 48px rgba(0,0,0,.18),
                0  4px 12px rgba(0,0,0,.12);
        "
            x-data="{ openSubtypeKey: null }">

            {{-- MODAL HEADER --}}
            <div
                style="
                display:flex;
                align-items:flex-start;
                justify-content:space-between;
                padding:1rem 1.25rem;
                border-bottom:1px solid #e5e7eb;
            ">
                <div>
                    <div style="font-size:.75rem;color:#6b7280;">
                        Add products for
                    </div>
                    <div style="font-size:1rem;font-weight:600;color:#111827;">
                        {{ $createTypeName }}
                    </div>
                    <div style="font-size:.75rem;color:#6b7280;">
                        Pick products per subtype. Search &amp; filter available.
                    </div>
                </div>

                <button
                    type="button"
                    wire:click="closeCreateModal"
                    class="btn btn-sm btn-outline-secondary"
                    style="font-size:.7rem;line-height:1.1rem;padding:.25rem .5rem;">
                    ✕ CLOSE
                </button>
            </div>

            {{-- MODAL BODY --}}
            <div style="padding:1rem 1.25rem;">

                <style>
                    /* CONTRACT-LIKE SUBTYPE HEADER ROWS */
                    .subtype-row {
                        border: 1px solid #cfd3dc;
                        border-radius: .5rem;
                        overflow: hidden;
                        margin-bottom: .5rem;
                    }

                    /* header button (accordion head) */
                    .subtype-head-btn {
                        width: 100%;
                        display: flex;
                        align-items: center;
                        justify-content: space-between;

                        /* spacing / typography */
                        padding: .75rem 1rem;
                        font-size: .9rem;
                        font-weight: 500;
                        line-height: 1.2rem;
                        color: #111827;

                        /* reset button look */
                        background: transparent;
                        border: 0;
                        border-radius: 0;
                        text-align: left;
                    }

                    /* left block inside head */
                    .subtype-head-left {
                        display: flex;
                        align-items: center;
                        flex-wrap: wrap;
                        gap: .5rem;
                    }

                    /* "SUBTYPE" pill, grey bordered like screenshot */
                    .badge-chip-light {
                        background: #f9fafb;
                        color: #374151;
                        border-radius: .5rem;
                        border: 1px solid #d1d5db;
                        font-size: .7rem;
                        line-height: 1rem;
                        padding: .15rem .5rem;
                        font-weight: 500;
                    }

                    /* optional "SELECTED" style (green pill) */
                    .badge-chip-selected {
                        background: #198754;
                        color: #fff;
                        border-radius: .5rem;
                        font-size: .7rem;
                        line-height: 1rem;
                        padding: .15rem .5rem;
                        font-weight: 600;
                    }

                    /* chevron rotates when open */
                    .subtype-chevron {
                        width: 20px;
                        height: 20px;
                        stroke: currentColor;
                        color: #111827;
                        flex-shrink: 0;
                        transition: transform .2s ease;
                    }

                    /* body wrapper under header */
                    .subtype-body-wrap {
                        border-top: 1px solid #cfd3dc;
                        background: #fff;
                        padding: 1rem;
                    }

                    /* ZEBRA BG FOR HEADER ROWS (striping like contract list)
       we use nth-child on .subtype-row */
                    .subtype-row:nth-child(odd) .subtype-head-btn {
                        background-color: #eef5ff;
                        /* soft very light blue */
                    }

                    .subtype-row:nth-child(even) .subtype-head-btn {
                        background-color: #fff7ed;
                        /* soft warm off-white */
                    }

                    /* when open, boost a tiny border emphasis */
                    .subtype-row.open {
                        border-color: #9ca3af;
                    }

                    /* TABLE STYLES (variant list table) */
                    .zone-table-wrapper {
                        border: 1px solid #cfd3dc;
                        border-radius: .5rem;
                        overflow: hidden;
                        background: #fff;
                    }

                    .zone-table {
                        width: 100%;
                        margin-bottom: 0;
                    }

                    .zone-table thead {
                        background: #0d6efd;
                        color: #fff;
                    }

                    .zone-table thead th {
                        font-size: .75rem;
                        font-weight: 600;
                        vertical-align: middle;
                        border-color: #0d6efd;
                        padding: .5rem .75rem;
                        white-space: nowrap;
                    }

                    .zone-table tbody tr:nth-child(odd) {
                        background-color: #f8f9ff;
                    }

                    .zone-table tbody tr:nth-child(even) {
                        background-color: #ffffff;
                    }

                    .zone-table tbody td {
                        font-size: .8rem;
                        color: #1f3b57;
                        vertical-align: middle;
                        border-color: #e5e7eb;
                        padding: .5rem .75rem;
                        line-height: 1.2rem;
                        white-space: nowrap;
                    }

                    .zone-table th:first-child,
                    .zone-table td:first-child {
                        width: 32px;
                        text-align: center;
                        white-space: nowrap;
                    }

                    .zone-table td.col-product {
                        font-weight: 500;
                        color: #1f2937;
                        white-space: normal;
                    }

                    .zone-compact-inputgroup .input-group-text {
                        background: #f3f4f6;
                        border-color: #d1d5db;
                        font-size: .75rem;
                        line-height: 1rem;
                        padding: .25rem .4rem;
                    }

                    .zone-compact-inputgroup input.form-control {
                        border-color: #d1d5db;
                        font-size: .8rem;
                        padding: .25rem .4rem;
                        line-height: 1.1rem;
                        height: auto;
                        min-width: 3rem;
                    }

                    .zone-mode-toggle .btn {
                        font-size: .7rem;
                        line-height: 1rem;
                        padding: .25rem .5rem;
                        min-width: 45px;
                    }

                    .zone-mode-toggle .btn-outline-primary {
                        background: #fff;
                    }
                </style>

                @forelse($modalSubtypeGroups as $idx => $subInfo)
                @php
                $subKey = 'sub_'.$idx;
                $subtypeLabel = $subInfo['subtype_label'] ?? '(No Name)';
                $subtypeSlug = $subInfo['subtype_slug'] ?? '';
                $subtypeId = $subInfo['subtype_id'] ?? null;
                @endphp

                <div
                    class="subtype-row"
                    :class="openSubtypeKey === '{{ $subKey }}' ? 'open' : ''">
                    {{-- HEADER (accordion trigger) --}}
                    <button
                        type="button"
                        class="subtype-head-btn"
                        @click="openSubtypeKey = (openSubtypeKey === '{{ $subKey }}' ? null : '{{ $subKey }}')">
                        <div class="subtype-head-left">
                            <span>{{ $subtypeLabel }}</span>

                            <span class="badge-chip-light">
                                SUBTYPE
                            </span>

                            {{-- if later you want green SELECTED pill, show it like this:
                <span class="badge-chip-selected">SELECTED</span>
                --}}
                        </div>

                        <svg
                            class="subtype-chevron"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            :style="openSubtypeKey === '{{ $subKey }}'
                    ? 'transform:rotate(180deg);'
                    : ''">
                            <path d="M6 9l6 6 6-6" />
                        </svg>
                    </button>

                    {{-- BODY (collapsible content) --}}
                    <div
                        class="subtype-body-wrap"
                        x-show="openSubtypeKey === '{{ $subKey }}'"
                        x-collapse>
                        {{-- THIS renders:
                             - search (q)
                             - onlyActive toggle
                             - table (Product / Variant / SKU / MRP / Mode / Discount / Cost / Active in Zone / checkbox)
                             - % / ₹ toggle and calculations
                             EXACTLY like your contract details page.
                        --}}
                        <livewire:signup.zone-variants-list
                            context="sub-change"
                            :category="$createTypeName"
                            :subtype="$subtypeSlug"
                            :type-id="null"
                            :subtype-id="$subtypeId"
                            :zone-id="(int) $zoneId"
                            :vendor-id="$vendorId ? (int) $vendorId : null"
                            :default-frequency="'daily'"
                            :default-start="now()->toDateString()"
                            :default-end="null"
                            :key="'modal-'.$idx.'-'.$zoneId.'-'.$subtypeSlug" />
                    </div>
                </div>

                @empty
                <div class="text-muted small py-3 text-center">
                    No subtypes found for {{ $createTypeName }} in this zone.
                </div>
                @endforelse

            </div>

            {{-- MODAL FOOTER --}}
            <div
                style="
                display:flex;
                align-items:center;
                justify-content:space-between;
                padding:.75rem 1.25rem;
                border-top:1px solid #e5e7eb;
                background:#fff;
                border-bottom-left-radius:.75rem;
                border-bottom-right-radius:.75rem;
            ">
                <button type="button"
                    wire:click="closeCreateModal"
                    class="btn btn-light"
                    style="
                    border-radius:.6rem;
                    border:1px solid #cbd3e7;
                    color:#1f3b57;
                    font-size:.8rem;
                    font-weight:500;
                    line-height:1.1rem;
                    padding:.4rem .75rem;
                ">
                    Cancel
                </button>

                <button type="button"
                    class="btn btn-primary"
                    wire:click="saveRequestNow"
                    style="
                    border-radius:.6rem;
                    background:#4a4cc7;
                    border-color:#4a4cc7;
                    font-size:.8rem;
                    font-weight:500;
                    line-height:1.1rem;
                    padding:.4rem .75rem;
                ">
                    + SAVE REQUEST
                </button>
            </div>

        </div>
    </div>
    @endif


    {{-- Alpine scripts --}}
    @once
    {{-- Only load Alpine collapse plugin IF Alpine is not already provided by layout.
         If your layout already imports Alpine (most apps do), then:
         1) keep only the collapse plugin here,
         2) DO NOT import alpinejs again here.
    --}}
    <script src="https://unpkg.com/@alpinejs/collapse@3.x.x/dist/cdn.min.js" defer></script>
    @endonce

</div>