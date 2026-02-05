@php
// ✅ Guard: if this file is accidentally included twice, bail to avoid recursion
static $renderedSidebar = false;
if ($renderedSidebar) { return; }
$renderedSidebar = true;

use Illuminate\Support\Facades\Route;

$authUser = auth()->user();

// role → partial map (partials should NOT @extends a layout)
$roleSidebarMap = [
'admin' => 'layouts.navbars.auth.sidebar-admin',
'customer' => 'layouts.navbars.auth.sidebar-customer',
'zones-head' => 'layouts.navbars.auth.sidebar-zones-head',
'zones-director' => 'layouts.navbars.auth.sidebar-zones-director',
'zone-manager' => 'layouts.navbars.auth.sidebar-zone-manager',
'vendor' => 'layouts.navbars.auth.sidebar-vendor',
'vendor-milk' => 'layouts.navbars.auth.sidebar-vendor-milk',
'vendor-vegetable' => 'layouts.navbars.auth.sidebar-vendor-vegetable',
'vendor-meat' => 'layouts.navbars.auth.sidebar-vendor-meat',
'vendor-grocery' => 'layouts.navbars.auth.sidebar-vendor-grocery',
'workman' => 'layouts.navbars.auth.sidebar-workman',
'workman-delivery-boy' => 'layouts.navbars.auth.sidebar-workman-delivery-boy',
'workman-washerman' => 'layouts.navbars.auth.sidebar-washerman',
'workman-plumber' => 'layouts.navbars.auth.sidebar-plumber',
'workman-delivery-boy-milk' => 'layouts.navbars.auth.sidebar-delivery-boy-milk',
'workman-delivery-boy-grocery' => 'layouts.navbars.auth.sidebar-delivery-boy-grocery',
'workman-delivery-boy-puja-items'=> 'layouts.navbars.auth.sidebar-delivery-boy-puja-items',
];

$roleOrder = [
'admin',
'zones-director','zones-head','zone-manager',
'vendor','vendor-milk','vendor-vegetable','vendor-grocery','vendor-meat',
'workman','workman-delivery-boy','workman-delivery-boy-milk','workman-delivery-boy-grocery','workman-delivery-boy-puja-items','workman-washerman','workman-plumber',
'customer',
];

$roles = $authUser?->getRoleNames()->toArray() ?? [];
usort($roles, function ($a, $b) use ($roleOrder) {
$ia = array_search($a, $roleOrder, true); $ia = $ia === false ? PHP_INT_MAX : $ia;
$ib = array_search($b, $roleOrder, true); $ib = $ib === false ? PHP_INT_MAX : $ib;
return $ia <=> $ib;
    });

    $renderedPartials = [];
    @endphp
    <aside id="sidenav-main" class="sidenav navbar navbar-vertical navbar-expand-xs border-0 border-radius-xl my-3 fixed-start sidebar-soft">

        <div class="sidenav-header"></div>

        <div class="collapse navbar-collapse w-auto h-100 d-flex flex-column" id="sidenav-collapse-main">
            <ul class="navbar-nav flex-grow-1 d-flex flex-column">

                {{-- HOME (top) --}}
                @include('layouts.navbars.auth.sidebar-home-section')

                {{-- MY WORK (second) --}}
                @include('layouts.navbars.auth.sidebar-mywork-section', ['roles' => $roles, 'roleSidebarMap' => $roleSidebarMap])

                {{-- COMMON sections after My Work --}}
                @include('layouts.navbars.auth.sidebar-common-sections')
            </ul>
        </div>

    </aside>