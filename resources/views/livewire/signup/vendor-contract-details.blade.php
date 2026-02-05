{{-- resources/views/livewire/signup/vendor-contract-details.blade.php --}}
<div class="vendor-contract-root"> {{-- ✅ single Livewire root --}}

    {{-- =========================
         Role selection
    ========================== --}}
    <div class="mb-4">
        <label class="form-label d-block mb-2 text-center">I am a</label>
        <div class="border rounded p-3 d-flex flex-column align-items-start gap-3"
            style="max-width:380px;margin:0 auto;">
            <div class="form-check">
                <input class="form-check-input"
                    type="radio"
                    id="roleVendor"
                    value="vendor"
                    wire:model="primaryType"
                    wire:change="onRoleChange($event.target.value)">
                <label class="form-check-label fw-semibold" for="roleVendor">Vendor</label>
            </div>
            <div class="form-check">
                <input class="form-check-input"
                    type="radio"
                    id="roleWorkman"
                    value="workman"
                    wire:model="primaryType"
                    wire:change="onRoleChange($event.target.value)">
                <label class="form-check-label fw-semibold" for="roleWorkman">Workman (Service Provider)</label>
            </div>
        </div>
    </div>

    {{-- =========================
         Category dropdown
    ========================== --}}
    {{-- Type --}}
    <div class="mb-3" style="max-width:420px;margin:0 auto;">
        <label class="form-label">{{ $primaryType === 'workman' ? 'Select a Service Type' : 'Select a Subscription Type' }}</label>
        <select class="form-select"
            wire:model.live="typeId" {{-- instant updates --}}
            @disabled(empty($primaryType))>
            <option value="" hidden>Select</option>
            @foreach(($subscriptionTypeOptions ?? []) as $id => $name)
            <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
        @if($subscription_type_id)
        <div class="form-text">Selected: {{ $subscriptionType }} (ID: {{ $subscription_type_id }})</div>
        @endif
    </div>

    {{-- Subtype --}}
    @if($subscription_type_id)
    <div class="text-muted small mb-2" style="max-width:980px;margin:0 auto;">
        Click a subtype below to select it.
        @if($subscription_subtype_id)
        <span class="ms-2">Selected: <strong>{{ $subscriptionSubtype }}</strong> (ID: {{ $subscription_subtype_id }})</span>
        @endif
    </div>
    @endif

    {{-- Hidden inputs so a normal <form> POST includes IDs too (if you need it) --}}
    <input type="hidden" name="subscription_type_id" value="{{ $subscription_type_id }}">
    <input type="hidden" name="subscription_subtype_id" value="{{ $subscription_subtype_id }}">


    {{-- =========================
         Section header
    ========================== --}}
    <div class="mb-2" style="max-width:980px;margin:0 auto;">
        <h6 class="mb-1 text-uppercase fw-bold" style="color:#2563eb;">CONTRACT DETAILS — V2</h6>
        <div class="small text-muted">Pick products per subtype. Search &amp; filter available.</div>
    </div>

    @php
    $canShow = filled($primaryType) && filled($subscription_type_id);
    $zoneId = $zoneId ?? 1;
    $vendorId= $vendorId ?? null;
    @endphp

    {{-- =========================
         Empty states / guidance
    ========================== --}}
    @if (!$canShow)
    <div class="alert alert-info text-center" style="max-width:980px;margin:0 auto;">
        Choose your role and a {{ $primaryType === 'workman' ? 'service type' : 'subscription type' }} to continue.
    </div>
    @elseif (empty($subtypes))
    <div class="alert alert-info text-center" style="max-width:980px;margin:0 auto;">
        No sub-types available for this selection.
    </div>
    @else
    {{-- =========================
             Accordions (mutually-exclusive)
        ========================== --}}
    <div x-data="{ openKey: null }"
        x-on:ui:close-all-accordions.window="openKey = null"
        class="dayli-acc border overflow-hidden"
        style="max-width:1400px;margin:0 auto;border-radius:.5rem;">

        <style>
            .dayli-acc .acc-head {
                cursor: pointer;
                user-select: none;
            }

            .dayli-acc .acc-item:nth-child(odd) .acc-head {
                background: #fff7ed;
            }

            /* light orange */
            .dayli-acc .acc-item:nth-child(even) .acc-head {
                background: #eff6ff;
            }

            /* light blue  */
            .dayli-acc .acc-head:hover {
                filter: brightness(.985);
            }

            .dayli-acc .chev {
                transition: transform .2s ease;
            }

            .dayli-acc [aria-expanded="true"] .chev {
                transform: rotate(180deg);
            }

            .dayli-acc .acc-body {
                background: #fff;
            }
        </style>

        @foreach ($subtypes as $i => $t)
        @php
        $sid = $t['id'] ?? null;
        $label = $t['label'] ?? '';
        $subKey= $t['key'] ?? ('s'.$i);
        $uid = 'acc_'.$subKey.'_'.$zoneId;
        @endphp

        <div class="acc-item border-bottom">
            <button type="button"
                class="w-100 d-flex align-items-center justify-content-between acc-head px-3 py-2 border-0"
                :class="openKey === '{{ $subKey }}' ? 'bg-primary text-white' : ''"
                @click="openKey = (openKey === '{{ $subKey }}' ? null : '{{ $subKey }}')"
                @if($sid) wire:click="selectSubtype({{ (int)$sid }}, '{{ $label }}')" @endif
                :aria-expanded="openKey === '{{ $subKey }}' ? 'true' : 'false'"
                :aria-controls="'{{ $uid }}'">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-semibold">{{ $label }}</span>
                    <span class="badge rounded-pill bg-light text-muted border">Subtype</span>
                    @if(!is_null($subscription_subtype_id) && $subscription_subtype_id === (int)($sid ?? -1))
                    <span class="badge rounded-pill bg-success">Selected</span>
                    @endif
                </div>
                <svg class="chev" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

            <div id="{{ $uid }}" class="acc-body px-3 pb-3" x-show="openKey === '{{ $subKey }}'" x-collapse>
                <livewire:signup.zone-variants-list
                    :category="$subscriptionType"
                    :subtype="$label"
                    :type-id="$subscription_type_id"
                    :subtype-id="$sid"
                    :zone-id="(int) $zoneId"
                    :vendor-id="$vendorId ? (int) $vendorId : null"
                    :default-frequency="$frequency_type" {{-- NEW --}}
                    :default-start="$start_date" {{-- NEW --}}
                    :default-end="$end_date" {{-- NEW --}}
                    context="contract" {{-- NEW: makes the child emit to VendorContractDetails --}}
                    :key="'subtype-'.$sid.'-zone'.$zoneId" />
            </div>
        </div>
        @endforeach
    </div>

    @once
    {{-- Alpine (collapse) --}}
    <script src="https://unpkg.com/@alpinejs/collapse@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    @endonce
    @endif
</div>