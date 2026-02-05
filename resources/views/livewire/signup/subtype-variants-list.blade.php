{{-- resources/views/livewire/signup/subtype-variants-list.blade.php --}}
{{--
     Wrapper view: delegates everything to ZoneVariantsList.
     This ensures old references to SubtypeVariantsList keep working.
--}}

<div class="w-100">
    <livewire:signup.zone-variants-list
        :category="$category"
        :subtype="$subtype"
        :zone-id="$zoneId"
        :vendor-id="$vendorId"
        :key="'zvl-'.$zoneId.'-'.strtolower(preg_replace('/\s+/', '_', $category)).'-'.strtolower(preg_replace('/\s+/', '_', $subtype))" />
</div>