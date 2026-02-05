@php
use Illuminate\Support\Facades\Cache;
use App\Models\SubscriptionType;
use App\Models\VendorZoneSubscr; // pivot that links vendor ↔ zone ↔ subscription types

$user = auth()->user();
$roles = $user?->getRoleNames()?->toArray() ?? [];
$showMyWork = $user && ! in_array('customer', $roles, true);

// For vendor: fetch the subscription types the vendor actually works on (per zone if needed)
$vendorTypes = [];
if ($showMyWork && $user->hasRole(['vendor','vendor-milk','vendor-grocery','vendor-vegetable','vendor-meat'])) {
$vendorTypes = Cache::remember("vendor:{$user->id}:mywork:types", 300, function() use ($user) {
return SubscriptionType::query()
->select('subscription_types.id','subscription_types.name','subscription_types.slug')
->join('vendor_zone_subscr as vzs','vzs.subscription_type_id','=','subscription_types.id')
->where('vzs.vendor_id', $user->id)
->where('vzs.is_active', true)
->distinct()
->orderBy('subscription_types.name')
->get();
});
}
@endphp

@if($showMyWork)
@component('layouts.navbars.auth.partials.section', ['title'=>'My Work','key'=>'sec.mywork','defaultOpen'=>true])
{{-- Generic quick links for ops roles (optional) --}}
@if($user->hasAnyRole(['admin','zones-director','zones-head','zone-manager']))
<li class="nav-item">
    <a href="{{ route('mywork.overview') }}" class="nav-link {{ request()->routeIs('mywork.overview') ? 'active' : '' }}">
        <i class="ni ni-bullet-list-67"></i>
        <span class="nav-link-text ms-1">Ops Overview</span>
    </a>
</li>
<li class="nav-item">
    <a href="{{ route('mywork.reconciliation') }}" class="nav-link {{ request()->routeIs('mywork.reconciliation') ? 'active' : '' }}">
        <i class="ni ni-ungroup"></i>
        <span class="nav-link-text ms-1">Reconciliation</span>
    </a>
</li>
@endif

{{-- Delivery Executive links --}}
@if($user->hasAnyRole(['workman','workman-delivery-boy','workman-delivery-boy-milk','workman-delivery-boy-grocery']))
<li class="nav-item">
    <a href="{{ route('mywork.delivery.actuals') }}" class="nav-link {{ request()->routeIs('mywork.delivery.*') ? 'active' : '' }}">
        <i class="ni ni-delivery-fast"></i>
        <span class="nav-link-text ms-1">Deliveries / Actuals</span>
    </a>
</li>
@endif

{{-- Vendor supplies — one entry per Subscription Type --}}
@if($user->hasAnyRole(['vendor','vendor-milk','vendor-vegetable','vendor-meat','vendor-grocery']))
@foreach($vendorTypes as $t)
<li class="nav-item">
    <a href="{{ route('mywork.vendor.type', $t->id) }}" class="nav-link {{ request()->routeIs('mywork.vendor.type') && request()->route('typeId') == $t->id ? 'active' : '' }}">
        <i class="ni ni-cart"></i>
        <span class="nav-link-text ms-1">{{ $t->name }}</span>
    </a>
</li>
@endforeach
@endif
@endcomponent
@endif