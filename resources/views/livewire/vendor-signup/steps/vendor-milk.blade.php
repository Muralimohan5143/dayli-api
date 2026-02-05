{{-- resources/views/livewire/vendor-signup/steps/vendor-milk.blade.php --}}
@php
$subscriptionType = 'milk_dairy';

$vendorTypes = [
'milk_dairy' => 'Milk & Dairy','vegetables'=>'Vegetables','fruits'=>'Fruits','beverages'=>'Beverages',
'bakery_snacks'=>'Bakery & Snacks','fish_seafood'=>'Fish & Seafood','meat'=>'Meat','flowers'=>'Flowers',
'groceries'=>'Groceries','puja_samagri'=>'Puja Samagri','chaats_quick_snacks'=>'Chaats & Quick Snacks',
'sweets_confectionery'=>'Sweets & Confectionery','health_packs'=>'Health Packs','services'=>'Services',
];

$subtypes = ['Milk','Curd','Paneer','Cheese','Ghee'];

$sectionTitle = ($vendorTypes[$subscriptionType] ?? 'Category') . ' — Sub-types';

// allow override from parent; otherwise defaults:
$mrpCatalog = ($mrpCatalog ?? []) ?: [
'milk'=>48, 'curd'=>35, 'paneer'=>380, 'cheese'=>420, 'ghee'=>520,
];
@endphp

{{-- shared styles for contract table --}}
@include('livewire.vendor-signup.partials.contract-styles')

{{-- pricing table --}}
@include('livewire.vendor-signup.partials.contract-table', [
'subtypes' => $subtypes,
'subscriptionType' => $subscriptionType,
'sectionTitle' => $sectionTitle,
'mrpCatalog' => $mrpCatalog,
'subtypesSelectedMap' => $subtypesSelectedMap ?? [],
])