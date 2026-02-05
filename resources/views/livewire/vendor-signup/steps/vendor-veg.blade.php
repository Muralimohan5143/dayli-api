{{-- resources/views/livewire/vendor-signup/steps/vendor-veg.blade.php --}}
@php
$subscriptionType = 'vegetables';

$vendorTypes = [
'milk_dairy' => 'Milk & Dairy','vegetables'=>'Vegetables','fruits'=>'Fruits','beverages'=>'Beverages',
'bakery_snacks'=>'Bakery & Snacks','fish_seafood'=>'Fish & Seafood','meat'=>'Meat','flowers'=>'Flowers',
'groceries'=>'Groceries','puja_samagri'=>'Puja Samagri','chaats_quick_snacks'=>'Chaats & Quick Snacks',
'sweets_confectionery'=>'Sweets & Confectionery','health_packs'=>'Health Packs','services'=>'Services',
];

// Display labels; keys (slugs) will be derived in the table via Str::slug(...)
$subtypes = ['Vegetables','Leafy Veg','Vegetable Juice','Spices','Diabetic Friendly Veg Pack'];

// Optional live counter in title
$selectedCount = count($subtypesSelectedMap[$subscriptionType] ?? []);
$totalSubtypes = count($subtypes);
$sectionTitle = ($vendorTypes[$subscriptionType] ?? 'Category')
. ' — Sub-types'
. " — {$selectedCount} / {$totalSubtypes} selected";

// Default MRP catalog (keys must be slugs of labels above)
// Adjust prices as needed.
$mrpCatalog = ($mrpCatalog ?? []) ?: [
'vegetables' => 40,
'leafy_veg' => 30,
'vegetable_juice' => 60,
'spices' => 80,
'diabetic_friendly_veg_pack' => 199,
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