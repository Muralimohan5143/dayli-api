<div
    x-data="{ open: @entangle('showCreateModal') }"
    x-cloak
>
    <!-- Backdrop -->
    <div
        x-show="open"
        style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1050;"
    ></div>

    <!-- Modal card -->
    <div
        x-show="open"
        style="
            position:fixed;
            z-index:1060;
            top:50%;
            left:50%;
            transform:translate(-50%, -50%);
            width:100%;
            max-width:600px;
            background:#ffffff;
            border-radius:1rem;
            box-shadow:0 25px 60px rgba(0,0,0,.25);
        "
        @keydown.escape.window="open=false"
    >
        {{-- HEADER --}}
        <div style="padding:1rem 1.25rem;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
                <div style="font-size:1rem;font-weight:600;color:#1f2937;line-height:1.3;">
                    Create Change Request
                </div>
                <div style="font-size:.8rem;color:#6b7280;line-height:1.2;margin-top:.25rem;">
                    Select products, set qty, and save as draft order.
                </div>
            </div>

            <button
                type="button"
                style="background:transparent;border:0;color:#6b7280;font-size:1rem;line-height:1;"
                @click="open=false"
            >
                ✕
            </button>
        </div>

        {{-- BODY --}}
        <div style="padding:1rem 1.25rem;max-height:60vh;overflow-y:auto;">

            {{-- Zone / Vendor / Subscription info preview --}}
            <div style="font-size:.8rem;color:#374151;margin-bottom:1rem;line-height:1.4;">
                <div><strong>Zone:</strong> {{ $zone_id }}</div>
                <div><strong>Vendor:</strong> {{ $vendor_id }}</div>
                <div><strong>Customer:</strong> {{ $customer_id }}</div>
                <div><strong>Type:</strong> {{ $subscriptionType }} (ID {{ $subscription_type_id }})</div>
                <div><strong>Subtype:</strong> {{ $subscriptionSubtype }} (ID {{ $subscription_subtype_id }})</div>
                <div><strong>Frequency:</strong> {{ $frequency_type }} {{ $custom_frequency_format ? '(' . $custom_frequency_format . ')' : '' }}</div>
                <div><strong>Invoice Cycle:</strong> {{ $invoice_cycle }}</div>
                <div><strong>Start:</strong> {{ $start_date }} <strong>End:</strong> {{ $end_date }}</div>
            </div>

            {{-- Change reason --}}
            <div class="mb-3" style="margin-bottom:1rem;">
                <label style="font-size:.8rem;font-weight:500;color:#374151;display:block;margin-bottom:.4rem;">
                    Change Reason
                </label>
                <textarea
                    class="form-control"
                    style="font-size:.8rem;border-radius:.5rem;border:1px solid #d1d5db;"
                    rows="2"
                    wire:model.defer="change_reason"
                    placeholder="Why are we creating / changing this request?"
                ></textarea>
                @error('change_reason')
                    <div style="color:#dc2626;font-size:.7rem;margin-top:.25rem;">{{ $message }}</div>
                @enderror
            </div>

            {{-- Product list UI --}}
            <div style="font-size:.8rem;font-weight:500;color:#374151;margin-bottom:.5rem;">
                Products in this request
            </div>

            <div style="border:1px solid #e5e7eb;border-radius:.5rem;overflow:hidden;">
                <table class="table mb-0" style="width:100%;font-size:.8rem;">
                    <thead style="background:#f9fafb;">
                        <tr>
                            <th style="padding:.5rem .75rem;">Variant</th>
                            <th style="padding:.5rem .75rem;width:60px;">Qty</th>
                            <th style="padding:.5rem .75rem;width:60px;">Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $idx => $row)
                            <tr>
                                <td style="padding:.5rem .75rem;vertical-align:top;line-height:1.3;">
                                    <div style="font-weight:500;color:#111827;">
                                        PID {{ $row['product_id'] ?? '-' }} /
                                        VID {{ $row['variant_id'] ?? '-' }}
                                    </div>

                                    {{-- You can show description / label here if you store it --}}
                                    {{-- <div style="color:#6b7280;font-size:.7rem;line-height:1.2;">
                                        {{ $row['title'] ?? '' }}
                                    </div> --}}
                                </td>

                                <td style="padding:.5rem .75rem;vertical-align:top;">
                                    <input
                                        type="text"
                                        class="form-control"
                                        style="font-size:.8rem;padding:.25rem .5rem;height:2rem;border-radius:.4rem;border:1px solid #d1d5db;"
                                        wire:model.lazy="items.{{ $idx }}.qty"
                                    />
                                </td>

                                <td style="padding:.5rem .75rem;vertical-align:top;">
                                    <input
                                        type="text"
                                        class="form-control"
                                        style="font-size:.8rem;padding:.25rem .5rem;height:2rem;border-radius:.4rem;border:1px solid #d1d5db;"
                                        wire:model.lazy="items.{{ $idx }}.unit"
                                    />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" style="padding:1rem .75rem;font-size:.8rem;color:#6b7280;">
                                    No products selected yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @error('items')
            <div style="color:#dc2626;font-size:.7rem;margin-top:.5rem;">{{ $message }}</div>
            @enderror
        </div>

        {{-- FOOTER --}}
        <div style="padding:1rem 1.25rem;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:.5rem;">
            <button type="button"
                class="btn btn-light"
                style="font-size:.8rem;border-radius:.6rem;padding:.4rem .75rem;line-height:1.1rem;"
                @click="open=false"
            >
                Cancel
            </button>

            <button type="button"
                class="btn btn-primary"
                style="
                    border-radius:.6rem;
                    background:#4a4cc7;
                    border-color:#4a4cc7;
                    font-size:.8rem;
                    font-weight:500;
                    line-height:1.1rem;
                    padding:.4rem .75rem;
                "
                wire:click="save"
            >
                Save Request
            </button>
        </div>
    </div>
</div>






<!-- <div>

    {{-- BODY CONTENT --}}
    @if(empty($subtypeList))
        <div class="alert alert-warning small mb-3"
            style="
                background:linear-gradient(90deg,#ffeb3b,#f44336);
                color:#111;
                border:0;
                font-size:.8rem;
                line-height:1.2rem;
            ">
            No products available for this type in this zone.
        </div>
    @else
        <div class="border rounded" style="border-color:#cfd3dc;">
            @foreach($subtypeList as $i => $block)
                @php
                    $bgRow = $i % 2 === 0 ? '#fff7ed' : '#eff6ff';
                @endphp

                <div
                    class="border-bottom"
                    style="border-color:#cfd3dc;">

                    {{-- subtype header --}}
                    <div
                        class="w-100 d-flex align-items-center justify-content-between text-start"
                        style="
                            background:{{ $bgRow }};
                            border:0;
                            padding:.6rem .75rem;
                            font-size:.9rem;
                            line-height:1.2rem;
                        ">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-semibold" style="color:#111827;">
                                {{ $block['subtype'] }}
                            </span>

                            <span
                                class="badge bg-light border text-muted"
                                style="font-size:.65rem;font-weight:500;">
                                SUBTYPE
                            </span>
                        </div>
                    </div>

                    {{-- product list --}}
                    <div style="padding:.75rem .75rem .5rem;">
                        @foreach($block['products'] as $prod)
                            <label
                                class="d-flex align-items-center justify-content-between border rounded mb-2"
                                style="
                                    padding:.5rem .75rem;
                                    font-size:.8rem;
                                    line-height:1.2rem;
                                    border-color:#e5e7eb;
                                    background:#fff;
                                ">
                                <div class="text-truncate" style="color:#111827;">
                                    {{ $prod['title'] }}
                                </div>

                                <input
                                    type="checkbox"
                                    wire:model="selectedProducts.{{ $prod['id'] }}"
                                    style="width:1rem;height:1rem;">
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- FOOTER ACTIONS --}}
    <div
        style="
            display:flex;
            align-items:center;
            justify-content:flex-end;
            gap:.5rem;
            padding:1rem 0 0;
        ">

        <button
            type="button"
            wire:click="cancelPanel"
            class="btn btn-outline-secondary"
            style="
                font-size:.8rem;
                line-height:1.1rem;
                padding:.45rem .8rem;
                border-radius:.5rem;
            ">
            CANCEL
        </button>

        <button
            type="button"
            wire:click="saveProducts"
            class="btn btn-primary"
            style="
                font-size:.8rem;
                line-height:1.1rem;
                padding:.45rem .8rem;
                border-radius:.5rem;
            ">
            SAVE SELECTION
        </button>
    </div>

</div> -->
