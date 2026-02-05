{{-- resources/views/livewire/vendor-signup/steps/vendor-fruits.blade.php --}}
@php
$subscriptionType = 'fruits';

$vendorTypes = [
'milk_dairy' => 'Milk & Dairy','vegetables'=>'Vegetables','fruits'=>'Fruits','beverages'=>'Beverages',
'bakery_snacks'=>'Bakery & Snacks','fish_seafood'=>'Fish & Seafood','meat'=>'Meat','flowers'=>'Flowers',
'groceries'=>'Groceries','puja_samagri'=>'Puja Samagri','chaats_quick_snacks'=>'Chaats & Quick Snacks',
'sweets_confectionery'=>'Sweets & Confectionery','health_packs'=>'Health Packs','services'=>'Services',
];

$subtypes = [
'Everyday Fruits','Seasonal Fruits','Imported Fruits','Fruit Juice','Smoothie',
'Dry Fruits','Diabetic Friendly Fruit Pack','Fruits Other'
];

// Optional live counter in title
$selectedCount = count($subtypesSelectedMap[$subscriptionType] ?? []);
$totalSubtypes = count($subtypes);
$sectionTitle = ($vendorTypes[$subscriptionType] ?? 'Category')
. ' — Sub-types'
. " — {$selectedCount} / {$totalSubtypes} selected";

// Default MRP catalog (keys must be slugs of labels above)
$mrpCatalog = ($mrpCatalog ?? []) ?: [
'everyday_fruits' => 80,
'seasonal_fruits' => 120,
'imported_fruits' => 180,
'fruit_juice' => 90,
'smoothie' => 120,
'dry_fruits' => 300,
'diabetic_friendly_fruit_pack' => 199,
'fruits_other' => 100,
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