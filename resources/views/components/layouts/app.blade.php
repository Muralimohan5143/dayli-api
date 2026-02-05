@php $isWizard = request()->routeIs('vendor.signup'); @endphp
@if (! $isWizard)
@include('layouts.navbars.auth.sidebar')
@endif