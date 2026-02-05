{{-- resources/views/livewire/vendor-signup/steps/vendor-beverages.blade.php --}}
@php
$subscriptionType = 'beverages';

$vendorTypes = [
'milk_dairy' => 'Milk & Dairy','vegetables'=>'Vegetables','fruits'=>'Fruits','beverages'=>'Beverages',
'bakery_snacks'=>'Bakery & Snacks','fish_seafood'=>'Fish & Seafood','meat'=>'Meat','flowers'=>'Flowers',
'groceries'=>'Groceries','puja_samagri'=>'Puja Samagri','chaats_quick_snacks'=>'Chaats & Quick Snacks',
'sweets_confectionery'=>'Sweets & Confectionery','health_packs'=>'Health Packs','services'=>'Services',
];

// Display labels; slugs are derived in the table via Str::slug(...)
$subtypes = ['Soft Drink','Soft Drinks','Energy Drink','Hot Drink','Hot Drinks','Fruit Juice','Smoothie'];

// Optional live counter in title
$selectedCount = count($subtypesSelectedMap[$subscriptionType] ?? []);
$totalSubtypes = count($subtypes);
$sectionTitle = ($vendorTypes[$subscriptionType] ?? 'Category')
. ' — Sub-types'
. " — {$selectedCount} / {$totalSubtypes} selected";

// Default MRP catalog (keys must match slugs of labels above)
$mrpCatalog = ($mrpCatalog ?? []) ?: [
'soft_drink' => 45,
'soft_drinks' => 80,
'energy_drink' => 110,
'hot_drink' => 60,
'hot_drinks' => 100,
'fruit_juice' => 90,
'smoothie' => 130,
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