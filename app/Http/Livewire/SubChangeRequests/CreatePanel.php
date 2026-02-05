<?php

/*namespace App\Http\Livewire\SubChangeRequests;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use App\Models\SubChangeRequest;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use Illuminate\Support\Facades\Auth;

class CreatePanel extends Component
{
    // modal visibility
    public $showCreateModal = false;

    // contextual info (must be set by parent OR via mount)
    public $zone_id;
    public $vendor_id;
    public $customer_id;

    // subscription details
    public $subscription_type_id;
    public $subscription_subtype_id;
    public $subscriptionType;      // human label like "Milk & Dairy"
    public $subscriptionSubtype;   // human label like "biscuits_and_chips"

    // cadence / billing / dates / reason
    public $frequency_type;
    public $custom_frequency_format;
    public $invoice_cycle;
    public $change_reason;
    public $start_date;
    public $end_date;

    // products the user selected in the modal
    // structure: each item = [
    //   'product_id' => 123,
    //   'variant_id' => 456,
    //   'qty' => '2.00',
    //   'unit' => 'pack',
    //   ... (optional pricing fields etc)
    // ]
    public $items = [];

    // if your Blade is still using modalCheckedProducts, we normalize it into $items before save()
    public $modalCheckedProducts = [];

    public $debugHit = 'not clicked';

    // parent (GroupedByType) will call $emit('openCreateModal')
    protected $listeners = [
        'openCreateModal' => 'openCreate',
    ];

    public function openCreate()
    {
        // clear old state and show modal
        $this->resetCreateFormState();
        $this->showCreateModal = true;
    }

    public function save()
    {
        // normalize modalCheckedProducts → items if needed
        if (empty($this->items) && !empty($this->modalCheckedProducts)) {
            $this->items = $this->modalCheckedProducts;
        }

        // validate: must have at least one product row
        if (empty($this->items) || count($this->items) === 0) {
            $this->dispatchBrowserEvent('cr-error', [
                'message' => 'Please select at least one product.',
            ]);
            return;
        }

        $byUserId  = Auth::id();      // who is making the request
        $forUserId = $this->customer_id; // target / customer

        try {
            DB::transaction(function () use ($byUserId, $forUserId) {

                // 1. create sub_change_requests
                $scr = SubChangeRequest::create([
                    'for_user_id'              => $forUserId,
                    'by_user_id'               => $byUserId,
                    'from_id'                  => null,
                    'draft_order_id'           => null,

                    'subscription_type_id'     => $this->subscription_type_id,
                    // if you DO have this column in DB uncomment:
                    // 'subscription_subtype_id'  => $this->subscription_subtype_id,

                    'subtypes_json'            => null,

                    'frequency_type'           => $this->frequency_type,
                    'custom_frequency_format'  => $this->custom_frequency_format,
                    'invoice_cycle'            => $this->invoice_cycle,

                    'change_reason'            => $this->change_reason,
                    'start_date'               => $this->start_date,
                    'end_date'                 => $this->end_date,

                    'action'                   => 'create',
                    'status'                   => 'pending',
                    'approved_by'              => null,
                    'approved_at'              => null,
                    'priority'                 => 3,

                    'payload'                  => [
                        'zone_id'     => $this->zone_id,
                        'customer_id' => $this->customer_id,
                        'type_label'  => $this->subscriptionType,
                        'sub_label'   => $this->subscriptionSubtype,
                    ],

                    'meta'                     => [
                        'debugHit' => $this->debugHit,
                    ],
                ]);

                // 2. create draft_orders
                $draft = DraftOrder::create([
                    'change_request_id'        => $scr->id,
                    'customer_id'              => $this->customer_id,
                    'vendor_id'                => $this->vendor_id,
                    'zone_id'                  => $this->zone_id,

                    'subscription_type_id'     => $this->subscription_type_id,
                    'subscription_subtype_id'  => $this->subscription_subtype_id,

                    'cadence'                  => $this->frequency_type,
                    'custom_frequency_format'  => $this->custom_frequency_format,
                    'invoice_cycle'            => $this->invoice_cycle,

                    'start_date'               => $this->start_date,
                    'end_date'                 => $this->end_date,

                    'status'                   => 'active',
                    'locked_at'                => null,
                    'timezone'                 => 'Asia/Kolkata',
                    'title'                    => 'Vendor Contract Draft',
                    'pricing_policy'           => null,
                    'tax_policy'               => null,
                    'meta'                     => null,
                ]);

                // 2.5. link back draft_order_id on sub_change_requests
                $scr->update([
                    'draft_order_id' => $draft->id,
                ]);

                // 3. upsert draft_order_items
                if (!empty($this->items)) {
                    $now  = now();
                    $rows = [];

                    foreach ($this->items as $it) {
                        $rows[] = [
                            'draft_order_id'    => $draft->id,
                            'product_id'        => Arr::get($it, 'product_id'),
                            'variant_id'        => Arr::get($it, 'variant_id'),

                            'vendor_id'         => $this->vendor_id,
                            'qty'               => Arr::get($it, 'qty', 1.00),
                            'unit'              => Arr::get($it, 'unit', 'pcs'),

                            // uncomment if you already capture pricing fields etc. in UI:
                            // 'price_snapshot'    => Arr::get($it, 'price_snapshot'),
                            // 'meta'              => Arr::get($it, 'meta'),
                            // 'vendor_base_rate'  => Arr::get($it, 'vendor_base_rate'),
                            // 'commission_type'   => Arr::get($it, 'commission_type', 'percent'),
                            // 'commission_value'  => Arr::get($it, 'commission_value', 0),
                            // 'customer_price'    => Arr::get($it, 'customer_price'),
                            // 'gst_rate'          => Arr::get($it, 'gst_rate'),
                            // 'pack_size'         => Arr::get($it, 'pack_size'),
                            // 'cutoff_time'       => Arr::get($it, 'cutoff_time'),
                            // 'lead_time_minutes' => Arr::get($it, 'lead_time_minutes', 0),
                            // 'active'            => Arr::get($it, 'active', true),

                            'created_at'        => $now,
                            'updated_at'        => $now,
                        ];
                    }

                    if (!empty($rows)) {
                        DraftOrderItem::upsert(
                            $rows,
                            ['draft_order_id', 'variant_id', 'vendor_id'], // unique key
                            [
                                'qty',
                                'unit',
                                'price_snapshot',
                                'meta',
                                'updated_at',
                            ]
                        );
                    }
                }
            });

            // success => close modal, reset, tell UI
            $this->resetCreateFormState();

            // browser toast/snackbar
            $this->dispatchBrowserEvent('cr-saved', [
                'message' => 'Request saved.',
            ]);

            // tell parent to refresh the grouped list on screen
            $this->dispatch('refreshGroupView');

        } catch (\Throwable $e) {
            report($e);

            $this->dispatchBrowserEvent('cr-error', [
                'message' => 'Save failed. Please try again.',
            ]);
        }
    }

    protected function resetCreateFormState()
    {
        $this->showCreateModal           = false;

        $this->subscription_type_id      = null;
        $this->subscription_subtype_id   = null;
        $this->subscriptionType          = null;
        $this->subscriptionSubtype       = null;

        $this->frequency_type            = null;
        $this->custom_frequency_format   = null;
        $this->invoice_cycle             = null;
        $this->change_reason             = null;
        $this->start_date                = null;
        $this->end_date                  = null;

        $this->items                     = [];
        $this->modalCheckedProducts      = [];

        $this->debugHit                  = 'not clicked';
    }

    public function render()
    {
        // you can load dropdown data etc. here if needed
        return view('livewire.sub-change-requests.create-panel');
    }
}





















// <?php

// namespace App\Http\Livewire\SubChangeRequests;

// use Livewire\Component;
// use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\DB;

// class CreatePanel extends Component
// {
//     public $zoneId;
//     public $vendorId;

//     public $createTypeName;        // e.g. "Bakery & Snacks"
//     public $subtypeList = [];      // array of subtypes => products
//     public $selectedProducts = []; // [ product_id => true ]

//     public $subscriptionTypeId = null;
//     public $subscriptionTypeName = null;

//     // optional advanced stuff
//     public $modalSubtypeOptions = [];
//     public $modalSelectedSubtypeId = null;
//     public $modalSelectedSubtypeName = null;
//     public $modalSubtypeGroups = [];
//     public $modalCheckedProducts = [];
//     public $modalSearch = '';




//     protected $listeners = [
//     'panelSaved' => 'onPanelSaved',
//     'panelCanceled' => 'onPanelCanceled',
// ];

//     public function mount($zoneId, $vendorId, $typeName)
//     {
//         $this->zoneId   = $zoneId ?? 1;
//         $this->vendorId = $vendorId ?? Auth::id();

//         $this->createTypeName       = $typeName;
//         $this->subscriptionTypeName = $typeName;

//         $this->subscriptionTypeId = $this->resolveSubscriptionTypeIdByName($typeName);

//         $this->buildSubtypeListDataSafe($typeName);
//         $this->prepareContractSubtypeDropdownSafe();
//     }

//     /**
//      * User hit CANCEL / CLOSE on modal
//      */
//     public function cancelPanel()
// {
//     // Just dispatch an event called 'panelCanceled'
//     $this->dispatch('panelCanceled');
// }

// public function saveProducts()
// {
//     $productIds = array_keys(array_filter($this->selectedProducts));

//     foreach ($productIds as $pid) {
//         DB::table('sub_change_request_items')->insert([
//             'vendor_id'  => $this->vendorId,
//             'zone_id'    => $this->zoneId,
//             'type'       => $this->createTypeName,
//             'product_id' => $pid,
//             'qty'        => 0,
//             'unit'       => 'pcs',
//             'created_at' => now(),
//             'updated_at' => now(),
//         ]);
//     }

//     // dispatch event up
//     $this->dispatch('panelSaved');
// }
//     protected function resolveSubscriptionTypeIdByName($typeName)
//     {
//         try {
//             $row = DB::table('subscription_types')
//                 ->select('id')
//                 ->where('name', $typeName)
//                 ->first();
//             return $row ? $row->id : null;
//         } catch (\Throwable $e) {
//             return null;
//         }
//     }

//     protected function buildSubtypeListDataSafe($typeName)
//     {
//         $this->subtypeList = [];

//         try {
//             $rows = DB::table('zone_product_variants as zpv')
//                 ->join('products as p', 'p.product_id', '=', 'zpv.product_id')
//                 ->select(
//                     'p.product_id',
//                     'p.product_sub_type as subtype',
//                     'p.title'
//                 )
//                 ->where('zpv.zone_id', $this->zoneId)
//                 ->where('p.product_type', 'like', '%' . $typeName . '%')
//                 ->orderBy('p.product_sub_type')
//                 ->orderBy('p.title')
//                 ->get();

//             $grouped = [];
//             foreach ($rows as $r) {
//                 $st = $r->subtype ?: 'Other';

//                 if (!isset($grouped[$st])) {
//                     $grouped[$st] = [
//                         'subtype'  => $st,
//                         'products' => [],
//                     ];
//                 }

//                 $grouped[$st]['products'][] = [
//                     'id'    => $r->product_id,
//                     'title' => $r->title,
//                 ];
//             }

//             $this->subtypeList = array_values($grouped);
//         } catch (\Throwable $e) {
//             $this->subtypeList = [];
//         }
//     }

//     protected function prepareContractSubtypeDropdownSafe()
//     {
//         $this->modalSubtypeOptions = [];

//         try {
//             if (!$this->subscriptionTypeId) {
//                 return;
//             }

//             $rows = DB::table('subscription_sub_types as sst')
//                 ->select('sst.id', 'sst.name')
//                 ->where('sst.subscription_type_id', $this->subscriptionTypeId)
//                 ->orderBy('sst.name')
//                 ->get();

//             $this->modalSubtypeOptions = $rows->map(function ($r) {
//                 return [
//                     'id'   => $r->id,
//                     'name' => $r->name,
//                 ];
//             })->toArray();
//         } catch (\Throwable $e) {
//             $this->modalSubtypeOptions = [];
//         }
//     }

//     public function updatedModalSelectedSubtypeId($val)
//     {
//         $chosen = collect($this->modalSubtypeOptions)->firstWhere('id', (int) $val);
//         $this->modalSelectedSubtypeName = $chosen['name'] ?? null;
//         $this->loadModalGroups();
//     }

//     public function updatedModalSearch()
//     {
//         $this->loadModalGroups();
//     }

//     public function loadModalGroups()
//     {
//         if (!$this->zoneId || !$this->subscriptionTypeName) {
//             $this->modalSubtypeGroups = [];
//             return;
//         }

//         $q = DB::table('zone_product_variants as zpv')
//             ->join('products as p', 'p.product_id', '=', 'zpv.product_id')
//             ->select([
//                 'p.product_type      as typ',
//                 'p.product_sub_type  as stype',
//                 'p.title             as product_title',
//             ])
//             ->where('zpv.zone_id', $this->zoneId)
//             ->where('p.product_type', 'LIKE', '%' . $this->subscriptionTypeName . '%');

//         if ($this->modalSelectedSubtypeName) {
//             $q->where('p.product_sub_type', $this->modalSelectedSubtypeName);
//         }

//         if (trim($this->modalSearch) !== '') {
//             $term = '%' . trim($this->modalSearch) . '%';
//             $q->where('p.title', 'LIKE', $term);
//         }

//         $rows = $q
//             ->groupBy('typ', 'stype', 'product_title')
//             ->orderBy('typ')
//             ->orderBy('stype')
//             ->orderBy('product_title')
//             ->get();

//         $grouped = [];
//         foreach ($rows as $r) {
//             $subtypeKey = $r->stype ?: '(No Subtype)';
//             if (!isset($grouped[$subtypeKey])) {
//                 $grouped[$subtypeKey] = [
//                     'subtype_name' => $subtypeKey,
//                     'products'     => [],
//                 ];
//             }

//             $grouped[$subtypeKey]['products'][] = [
//                 'title'   => $r->product_title,
//                 'checked' => $this->modalCheckedProducts[$r->product_title] ?? false,
//             ];
//         }

//         $this->modalSubtypeGroups = array_values($grouped);
//     }

//     public function toggleProductCheck($productTitle)
//     {
//         $cur = $this->modalCheckedProducts[$productTitle] ?? false;
//         $this->modalCheckedProducts[$productTitle] = !$cur;
//         $this->loadModalGroups();
//     }

//     public function render()
//     {
//         return view('livewire.sub-change-requests.create-panel', [
//             'createTypeName'    => $this->createTypeName,
//             'subtypeList'       => $this->subtypeList,
//             'selectedProducts'  => $this->selectedProducts,
//         ]);
//     }
// }

