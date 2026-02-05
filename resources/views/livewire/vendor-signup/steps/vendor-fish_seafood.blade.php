{{-- resources/views/livewire/vendor-signup/steps/vendor-fish_seafood.blade.php --}}
@php
$subscriptionType = 'fish_seafood';

$vendorTypes = [
'milk_dairy' => 'Milk & Dairy','vegetables'=>'Vegetables','fruits'=>'Fruits','beverages'=>'Beverages',
'bakery_snacks'=>'Bakery & Snacks','fish_seafood'=>'Fish & Seafood','meat'=>'Meat','flowers'=>'Flowers',
'groceries'=>'Groceries','puja_samagri'=>'Puja Samagri','chaats_quick_snacks'=>'Chaats & Quick Snacks',
'sweets_confectionery'=>'Sweets & Confectionery','health_packs'=>'Health Packs','services'=>'Services',
];

$subtypes = ['Freshwater Fish','Marine Fish','Prawns','Crabs','Oysters'];

// Optional counter in the title
$selectedCount = count($subtypesSelectedMap[$subscriptionType] ?? []);
$totalSubtypes = count($subtypes);
$sectionTitle = ($vendorTypes[$subscriptionType] ?? 'Category')
. ' — Sub-types'
. " — {$selectedCount} / {$totalSubtypes} selected";

// Default MRP catalog (keys must be slugs of labels above)
$mrpCatalog = ($mrpCatalog ?? []) ?: [
'freshwater_fish' => 300,
'marine_fish' => 350,
'prawns' => 500,
'crabs' => 600,
'oysters' => 450,
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