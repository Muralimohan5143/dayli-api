{{-- resources/views/livewire/vendor-signup/steps/vendor-groceries.blade.php --}}
@php
$subscriptionType = 'groceries';

$vendorTypes = [
'milk_dairy' => 'Milk & Dairy','vegetables'=>'Vegetables','fruits'=>'Fruits','beverages'=>'Beverages',
'bakery_snacks'=>'Bakery & Snacks','fish_seafood'=>'Fish & Seafood','meat'=>'Meat','flowers'=>'Flowers',
'groceries'=>'Groceries','puja_samagri'=>'Puja Samagri','chaats_quick_snacks'=>'Chaats & Quick Snacks',
'sweets_confectionery'=>'Sweets & Confectionery','health_packs'=>'Health Packs','services'=>'Services',
];

$subtypes = [
'Cereals And Pulses','Edible Oils','Spices','Groceries Other',
'Kitchen','Kitchen Other','Toiletries And Detergents'
];

// Optional live counter in title
$selectedCount = count($subtypesSelectedMap[$subscriptionType] ?? []);
$totalSubtypes = count($subtypes);
$sectionTitle = ($vendorTypes[$subscriptionType] ?? 'Category')
. ' — Sub-types'
. " — {$selectedCount} / {$totalSubtypes} selected";

// Default MRP catalog (keys must match Str::slug(...) of labels above)
$mrpCatalog = ($mrpCatalog ?? []) ?: [
'cereals_and_pulses' => 90,
'edible_oils' => 160,
'spices' => 80,
'groceries_other' => 70,
'kitchen' => 120,
'kitchen_other' => 100,
'toiletries_and_detergents' => 180,
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