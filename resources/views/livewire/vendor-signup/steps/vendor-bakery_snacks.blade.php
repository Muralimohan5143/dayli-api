{{-- resources/views/livewire/vendor-signup/steps/vendor-bakery_snacks.blade.php --}}
@php
$subscriptionType = 'bakery_snacks';

$vendorTypes = [
'milk_dairy' => 'Milk & Dairy','vegetables'=>'Vegetables','fruits'=>'Fruits','beverages'=>'Beverages',
'bakery_snacks'=>'Bakery & Snacks','fish_seafood'=>'Fish & Seafood','meat'=>'Meat','flowers'=>'Flowers',
'groceries'=>'Groceries','puja_samagri'=>'Puja Samagri','chaats_quick_snacks'=>'Chaats & Quick Snacks',
'sweets_confectionery'=>'Sweets & Confectionery','health_packs'=>'Health Packs','services'=>'Services',
];

$subtypes = ['Bread And Buns','Cakes And Pastries','Biscuits And Chips','Puffs','Bakery Other'];

// Optional live counter in title
$selectedCount = count($subtypesSelectedMap[$subscriptionType] ?? []);
$totalSubtypes = count($subtypes);
$sectionTitle = ($vendorTypes[$subscriptionType] ?? 'Category')
. ' — Sub-types'
. " — {$selectedCount} / {$totalSubtypes} selected";

// Default MRP catalog (keys must match Str::slug(...) of labels above)
$mrpCatalog = ($mrpCatalog ?? []) ?: [
'bread_and_buns' => 40,
'cakes_and_pastries' => 250,
'biscuits_and_chips' => 60,
'puffs' => 35,
'bakery_other' => 50,
];
@endphp

{{-- shared styles --}}
@includeIf('livewire.vendor-signup.partials.contract-styles')

{{-- pricing table --}}
@include('livewire.vendor-signup.partials.contract-table', [
'subtypes' => $subtypes,
'subscriptionType' => $subscriptionType,
'sectionTitle' => $sectionTitle,
'mrpCatalog' => $mrpCatalog,
'subtypesSelectedMap' => $subtypesSelectedMap ?? [],
])