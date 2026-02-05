@php
/** @var array $subtypes // e.g. ['Milk','Curd',...]
@var string $subscriptionType // e.g. 'milk_dairy'
@var string $sectionTitle // e.g. 'Milk & Dairy — Sub-types'
@var array $mrpCatalog // e.g. ['milk'=>48, 'curd'=>35, ...]
@var array $subtypesSelectedMap
@var array $pricing
@var array $discountMode
**/
@endphp

<div class="mt-3">
    <div class="border rounded">
        <div class="px-3 pt-3">
            <h6 class="mb-2 text-uppercase fw-bold" style="color:#2563eb;">
                {{ $sectionTitle }}
            </h6>
        </div>

        <div class="px-3 pb-3">
            <div class="table-responsive">
                <table class="dayli-table table align-middle">
                    <thead>
                        <tr>
                            <th style="min-width:260px;">Name</th>
                            <th>MRP</th>
                            <th>Discount %</th>
                            <th>Discount Amount</th>
                            <th>Cost Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($subtypes as $label)
                        @php
                        $key = \Illuminate\Support\Str::slug($label, '_'); // stable key per row
                        $mrp = $mrpCatalog[$key] ?? 0;
                        $checked = in_array($key, $subtypesSelectedMap[$subscriptionType] ?? []);
                        @endphp

                        <tr
                            wire:key="row-{{ $subscriptionType }}-{{ $key }}"
                            x-data="{
                  enabled: {{ $checked ? 'true' : 'false' }},

                  // Livewire <-> Alpine entangles (NEW primary keys)
                  mrp:  @entangle('pricing.'.$key.'.mrp').live,
                  pct:  @entangle('pricing.'.$key.'.percent').live,
                  amt:  @entangle('pricing.'.$key.'.amount').live,
                  cost: @entangle('pricing.'.$key.'.cost').live,

                  // discount mode (server map kept in sync)
                  mode: @entangle('discountMode.'.$key).live || 'percent',

                  recompute() {
                    if (!this.enabled) { this.cost = ''; return; }
                    const M = Number(this.mrp || 0);
                    let   P = Number(this.pct || 0);
                    let   A = Number(this.amt || 0);

                    // sanitize
                    if (P < 0) P = 0; if (P > 100) P = 100;
                    if (A < 0) A = 0; if (A > M)   A = M;

                    const discount = (this.mode === 'amount') ? A : (M * (P/100));
                    this.cost = (M - discount).toFixed(2);
                  }
                }"
                            x-init="if (mrp === null || mrp === undefined) mrp = {{ $mrp }}; recompute()"
                            x-effect="recompute()"
                            :class="enabled ? '' : 'is-off'">

                            {{-- NAME + checkbox --}}
                            <td>
                                <label class="d-flex align-items-center gap-2 mb-0">
                                    <input
                                        type="checkbox"
                                        @change="enabled = $event.target.checked"
                                        wire:model="subtypesSelectedMap.{{ $subscriptionType }}"
                                        value="{{ $key }}">
                                    <span class="fw-semibold text-dark">{{ $label }}</span>
                                </label>

                                {{-- keep legacy hidden fields (NOT removed) --}}
                                <input type="hidden" wire:model.live="pricing.{{ $key }}.name" value="{{ $label }}">
                                {{-- mirror to legacy keys too (so old code continues to work) --}}
                                <input type="hidden" wire:model.live="pricing.{{ $key }}.discount_pct" x-model="pct">
                                <input type="hidden" wire:model.live="pricing.{{ $key }}.discount_amt" x-model="amt">
                            </td>

                            {{-- MRP (read-only display, but synced to Livewire) --}}
                            <td style="width:120px">
                                <input
                                    type="number"
                                    class="form-control form-control-sm dayli-input text-dark"
                                    x-model="mrp"
                                    wire:model.live="pricing.{{ $key }}.mrp"
                                    readonly>
                            </td>

                            {{-- Discount % (with mode radio) --}}
                            <td style="width:160px">
                                <div class="d-flex align-items-center gap-2">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        :name="'mode_'+@js($key)"
                                        value="percent"
                                        x-model="mode">
                                    <div class="input-group input-group-sm dayli-input">
                                        <input
                                            type="number"
                                            min="0" max="100" step="0.01"
                                            class="form-control text-dark"
                                            placeholder="%"
                                            x-model="pct"
                                            :disabled="!enabled || mode !== 'percent'">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                            </td>

                            {{-- Discount Amount (with mode radio) --}}
                            <td style="width:180px">
                                <div class="d-flex align-items-center gap-2">
                                    <input
                                        class="form-check-input"
                                        type="radio"
                                        :name="'mode_'+@js($key)"
                                        value="amount"
                                        x-model="mode">
                                    <div class="input-group input-group-sm dayli-input">
                                        <span class="input-group-text">₹</span>
                                        <input
                                            type="number"
                                            min="0" step="0.01"
                                            class="form-control text-dark"
                                            placeholder="0.00"
                                            x-model="amt"
                                            :disabled="!enabled || mode !== 'amount'">
                                    </div>
                                </div>
                                <small class="d-block text-dark">Choose one: % or Amount</small>
                            </td>

                            {{-- Cost Price (read-only, computed) --}}
                            <td style="width:180px">
                                <div class="input-group input-group-sm dayli-input">
                                    <span class="input-group-text">₹</span>
                                    <input type="text" class="form-control text-dark" x-model="cost" readonly>
                                </div>
                            </td>

                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <small class="d-block text-dark">
                Tick a sub-type to enable pricing. Pick <strong>Discount %</strong> or <strong>Discount Amount</strong> (only one is active).
                Cost = MRP − Discount.
            </small>
        </div>
    </div>
</div>