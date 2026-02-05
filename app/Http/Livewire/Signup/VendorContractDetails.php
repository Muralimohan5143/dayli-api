<?php

namespace App\Http\Livewire\Signup;

use Livewire\Component;
use App\Models\SubscriptionType;
use App\Models\SubscriptionSubType;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Illuminate\Validation\Rule;
use App\Models\SubChangeRequest;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VendorContractDetails extends Component
{
    // =========================
    // OLD PROPS (kept as-is)
    // =========================

    // Backing fields for <select> (strings)
    public ?string $typeId    = null;
    public ?string $subtypeId = null;

    public $vendor_id;
    public $zone_id;
    public $customer_id;

    public $frequency_type = 'daily';
    public $custom_frequency_format;
    public $invoice_cycle = 'monthly';
    public $start_date;
    public $end_date;
    public $change_reason = 'self_service';
    // public $items = [];

    public string $primaryType = '';
    public array $subtypes = [];

    public ?int $zoneId = 1;
    public ?int $vendorId = null;

    public ?int $subscription_type_id    = null;   // ✅ important
    public ?int $subscription_subtype_id = null;   // ✅ important

    // (Optional) labels for showing text chips / preview
    public ?string $subscriptionType = null;
    public ?string $subscriptionSubtype = null;

    // Options for selects
    public array $subscriptionTypeOptions = [];   // [id => name]
    public array $subtypeOptions          = [];   // [id => name]

    public array $subscriptionsubtypeOptions = [];
    // add these to hold select options
    public array $typeOptions = [];      // [id => name]

    protected array $workmanTypeLabels = [
        'services'         => 'Services',
        'building_painter' => 'Building Painter',
        'carpenter'        => 'Carpenter',
        'cleaning'         => 'Cleaning',
        'cooking'          => 'Cooking',
        'electrical'       => 'Electrical',
        'gardening'        => 'Gardening',
        'home_security'    => 'Home Security',
        'plumbing'         => 'Plumbing',
    ];

    /** Cached map built from DB: [ 'Milk & Dairy' => ['milk','curd',...], ... ] */
    #[Locked]
    public array $typeToSubtypes = [];
    public array $rows = []; // keyed by variant_id
    public array $items = []; // final array to be saved in draft_order_items

    // =========================
    // NEW/ADDED PROPS (only added, nothing removed)
    // =========================

    /** When true, after picking a type we auto-pick the first subtype (optional) */
    public bool $autoPickFirstSubtype = true; // ADDED

    /** Map built from subscription tables with IDs (typeId => ['id','label','subtypes'=>[['id','key','label'],...]]) */
    public array $typeSubtypeIdMap = []; // ADDED


    /* ---------- Lifecycle ---------- */

    public function mount(?int $zoneId = null, ?int $vendorId = null): void
    {
        $this->vendorId  = $vendorId ?? session('signup_user_id');
        $this->zoneId    = $zoneId ?? 1;
        $this->vendor_id = $this->vendorId;
        $this->zone_id   = $this->zoneId;

        $this->start_date     = $this->start_date ?: now()->toDateString();
        $this->invoice_cycle  = $this->invoice_cycle ?: 'monthly';
        $this->frequency_type = $this->frequency_type ?: 'daily';

        // Load TYPE options from subscription_types
        $this->subscriptionTypeOptions = SubscriptionType::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        // No subtypes until a type is chosen
        $this->subtypeOptions = [];
        $this->subtypes       = [];

        // If IDs were prefilled, hydrate backing fields + subtypes
        if (!is_null($this->subscription_type_id)) {
            $this->typeId           = (string) $this->subscription_type_id;
            $this->subscriptionType = $this->subscriptionTypeOptions[$this->subscription_type_id] ?? null;
            $this->loadSubtypeOptions($this->subscription_type_id);
        }
        if (!is_null($this->subscription_subtype_id)) {
            $this->subtypeId           = (string) $this->subscription_subtype_id;
            $this->subscriptionSubtype = $this->subtypeOptions[$this->subscription_subtype_id] ?? null;
        }
    }

    public function setType($value): void
    {
        $id = (int) $value;

        $this->subscription_type_id    = $id;
        $this->subscriptionType        = $this->subscriptionTypeOptions[$id] ?? null;

        // reset subtype
        $this->subscription_subtype_id = null;
        $this->subscriptionSubtype     = null;

        $this->loadSubtypeOptions($id);

        // ADDED: optional auto-pick first subtype
        if ($this->autoPickFirstSubtype && !empty($this->subtypes)) {
            $first = $this->subtypes[0] ?? null;
            if ($first && isset($first['id'], $first['label'])) {
                $this->selectSubtype((int)$first['id'], (string)$first['label']);
            }
        }
    }

    public function setSubtype($value): void
    {
        $sid = (int) $value;

        $this->subscription_subtype_id = $sid;
        $this->subscriptionSubtype     = $this->subtypeOptions[$sid] ?? null;
    }

    public function updatedTypeId($value): void
    {
        $id = (int) $value;

        $this->subscription_type_id = $id;
        $this->subscriptionType     = $this->subscriptionTypeOptions[$id] ?? null;

        // reset subtype
        $this->subtypeId               = null;           // not used anymore, but reset anyway
        $this->subscription_subtype_id = null;
        $this->subscriptionSubtype     = null;

        $this->loadSubtypeOptions($id);

        // 👇 optional: pick first subtype automatically
        if ($this->autoPickFirstSubtype && !empty($this->subtypes)) {
            $first = $this->subtypes[0] ?? null; // ['id'=>..,'label'=>..]
            if ($first && isset($first['id'], $first['label'])) {
                $this->selectSubtype((int)$first['id'], (string)$first['label']);
            }
        }

        $this->dispatch('$refresh');
    }



    // When the SUBTYPE <select> changes (string -> int)
    public function updatedSubtypeId($value): void
    {
        $sid = (int) $value;

        $this->subscription_subtype_id = $sid;
        $this->subscriptionSubtype     = $this->subtypeOptions[$sid] ?? null;
    }


    private function loadTypeOptions(): void
    {
        $this->typeOptions = DB::table('subscription_types')
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')     // [id => name]
            ->toArray();
    }

    private function loadSubtypeOptions(int $typeId): void
    {
        // Dropdown options (id => name)
        $this->subtypeOptions = SubscriptionSubType::query()
            ->where('subscription_type_id', $typeId)
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        // Accordion list with IDs + labels + keys
        $this->subtypes = $this->subtypesFromSubscriptionSubTypes($typeId);
    }

    public function updatedSubscriptionTypeId($value): void
    {
        $typeId = (int) $value;

        // reset subtype
        $this->subscription_subtype_id = null;

        $this->subscription_type_id = (int) $value;
        $this->subscriptionSubtype  = null;
        // load subtypes by type id
        $this->subtypeOptions = DB::table('subscription_sub_types')
            ->where('subscription_type_id', $typeId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        // ADDED: also build the accordion rows based on this list (if not already built)
        $this->subtypes = collect($this->subtypeOptions)
            ->map(fn($name, $id) => ['id' => (int)$id, 'label' => $name, 'key' => 'st_' . $id])
            ->values()->all();
    }

    /* ---------- Explicit event handlers (bullet-proof) ---------- */
    public function onRoleChange(string $role): void
    {
        $this->primaryType = $role;

        $this->typeId    = null;
        $this->subtypeId = null;

        $this->subscription_type_id    = null;
        $this->subscription_subtype_id = null;

        $this->subscriptionType    = null;
        $this->subscriptionSubtype = null;

        $this->subtypeOptions = [];
        $this->subtypes       = [];
        $this->dispatch('ui:close-all-accordions');
    }
    public function onCategoryChange(string $value): void
    {
        $this->subscriptionType = $value;
        $this->subtypes         = $this->subtypesForTypeFromDb($value);
        $this->dispatch('ui:close-all-accordions');

        $this->subscription_type_id = (int) $value;
        $this->updatedSubscriptionTypeId($this->subscription_type_id);

        // ✅ NEW — get ID from DB for the selected type
        $typeId = DB::table('subscription_types')
            ->where('name', $value)
            ->orWhere('slug', $value)
            ->value('id');
        $this->subscription_type_id = $typeId;

        // ADDED: if we have our ID map, set label from map to be safe
        if ($typeId && isset($this->typeSubtypeIdMap[$typeId]['label'])) {
            $this->subscriptionType = $this->typeSubtypeIdMap[$typeId]['label'];
        }
    }

    public function updatedSubscriptionSubtypeId($value): void
    {
        $sid = (int) $value;
        $this->subscription_subtype_id = (int) $value;
        $this->subscriptionSubtype     = $this->subtypeOptions[$this->subscription_subtype_id] ?? null;
    }

    /** When user clicks a subtype accordion header */
    public function selectSubtype(int $id, string $label): void
    {
        $this->subscription_subtype_id = $id;
        $this->subscriptionSubtype     = $label;
    }

    // ✅ NEW — called by ZoneVariantList (or can be called manually)
    public function onSubtypeSelect(string $subtypeName): void
    {
        $this->subscriptionSubtype = $subtypeName;

        $subtypeId = DB::table('subscription_sub_types')
            ->where('name', $subtypeName)
            ->orWhere('slug', $subtypeName)
            ->value('id');

        $this->subscription_subtype_id = $subtypeId;
    }

    public array $form = []; // in component

    private function ensureFormDefaults(): void
    {
        // fill any missing keys so validation never complains
        $this->form = array_replace($this->defaults, $this->form);
    }

    private function normalize(): void
    {
        $this->hydrate(); // reuse same mapping
        if ($this->subscription_subtype_id === null && isset($this->subscription_sub_type_id)) {
            $this->subscription_subtype_id = $this->subscription_sub_type_id;
        }
        // also keep snake_case in sync with camel params (defensive)
        if ($this->vendor_id === null && $this->vendorId !== null)   $this->vendor_id = $this->vendorId;
        if ($this->zone_id   === null && $this->zoneId   !== null)   $this->zone_id   = $this->zoneId;
    }

    public function hydrate(): void
    {
        if ($this->vendor_id === null && $this->vendorId !== null) {
            $this->vendor_id = $this->vendorId;
        }
        if ($this->zone_id === null && $this->zoneId !== null) {
            $this->zone_id = $this->zoneId;
        }

        // unify subtype spelling
        if ($this->subscription_subtype_id === null && isset($this->subscription_sub_type_id)) {
            $this->subscription_subtype_id = $this->subscription_sub_type_id;
        }
    }



    protected function rules(): array
    {
        return [
            'vendor_id'                => ['required', 'exists:users,id'],
            'zone_id'                  => ['required', 'exists:zones,id'],
            'customer_id'              => ['nullable', 'exists:users,id'],
            'subscription_type_id'     => ['required', 'exists:subscription_types,id'],
            'frequency_type'           => ['required', Rule::in(['daily', 'alternate_days', 'weekdays', 'weekends', 'sat', 'sun', 'custom', 'on_demand'])],
            'invoice_cycle'            => ['required', Rule::in(['monthly', 'weekly', 'custom'])],
            'start_date'               => ['required', 'date'],
            'end_date'                 => ['nullable', 'date', 'after_or_equal:start_date'],
            'change_reason'            => ['required', Rule::in(['self_service', 'user-error', 'staff-error'])],
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.product_id'       => ['required', 'integer'],
            'items.*.variant_id'       => ['required', 'integer'],
            'items.*.qty'               => ['nullable', 'numeric', 'min:0.01'],
            'items.*.unit'              => ['nullable', 'string', 'max:16'],
            'items.*.price_snapshot'    => ['nullable', 'numeric', 'min:0'],
            'items.*.vendor_base_rate'  => ['nullable', 'numeric', 'min:0'],
            'items.*.commission_type'   => ['nullable', Rule::in(['percent', 'fixed'])],
            'items.*.commission_value'  => ['nullable', 'numeric', 'min:0'],
            'items.*.customer_price'    => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_rate'          => ['nullable', 'numeric', 'min:0', 'max:28'],
            'items.*.pack_size'         => ['nullable', 'string', 'max:64'],
            'items.*.cutoff_time'       => ['nullable', 'date_format:H:i:s'],
            'items.*.lead_time_minutes' => ['nullable', 'integer', 'min:0'],
            'items.*.active'            => ['nullable', 'boolean'],
        ];
    }


    #[On('contract:items-changed')]
    public function onItemsChanged(array $payload): void
    {
        $count = count($payload['items'] ?? []);
        $this->dispatch('toast', type: 'info', message: "Got $count items from child");

        foreach ($payload['items'] ?? [] as $it) {
            $vid = (int) ($it['variant_id'] ?? 0);
            $pid = (int) ($it['product_id'] ?? 0);
            if (! $vid || ! $pid) continue;

            $this->rows[$vid] = [
                'selected'          => true,
                'product_id'        => $pid,
                'qty'               => $it['qty'] ?? 1.00,
                'unit'              => $it['unit'] ?? 'pcs',
                'price_snapshot'    => $it['price_snapshot'] ?? null,
                'meta'              => $it['meta'] ?? null,
                'vendor_base_rate'  => $it['vendor_base_rate'] ?? null,
                'commission_type'   => $it['commission_type'] ?? 'percent',
                'commission_value'  => $it['commission_value'] ?? 0,
                'customer_price'    => $it['customer_price'] ?? null,
                'gst_rate'          => $it['gst_rate'] ?? null,
                'pack_size'         => $it['pack_size'] ?? null,
                'cutoff_time'       => $it['cutoff_time'] ?? null,
                'lead_time_minutes' => $it['lead_time_minutes'] ?? 0,
                'active'            => $it['active'] ?? true,

                // 👇 ADD THESE THREE (take from payload; fallback to previous row; then top-level)
                'frequency_type'    => $it['frequency_type']
                    ?? ($this->rows[$vid]['frequency_type'] ?? $this->frequency_type ?? 'daily'),
                'start_date'        => $it['start_date']
                    ?? ($this->rows[$vid]['start_date'] ?? $this->start_date ?? null),
                'end_date'          => $it['end_date']
                    ?? ($this->rows[$vid]['end_date'] ?? $this->end_date ?? null),
            ];
        }

        $this->buildItemsFromRows();
    }


    // Call this before $this->validate(...)
    private function buildItemsFromRows(): void
    {
        $items = [];

        foreach ((array) $this->rows as $variantId => $row) {
            if (empty($row['selected'])) continue;

            $variantId = (int) $variantId;
            $productId = (int) \Illuminate\Support\Arr::get($row, 'product_id');
            if (!$variantId || !$productId) continue;

            $qty      = (float) \Illuminate\Support\Arr::get($row, 'qty', 1.00);
            $unit     = (string) \Illuminate\Support\Arr::get($row, 'unit', 'pcs');
            $snapshot = \Illuminate\Support\Arr::get($row, 'price_snapshot');
            $meta     = \Illuminate\Support\Arr::get($row, 'meta');

            if (is_array($snapshot)) $snapshot = json_encode($snapshot);
            if (is_array($meta))     $meta     = json_encode($meta);

            // 🔑 take cadence from the ROW (fallback to top-level only if row empty)
            $rowFreq  = \Illuminate\Support\Arr::get($row, 'frequency_type', $this->frequency_type ?? 'daily');
            $rowStart = \Illuminate\Support\Arr::get($row, 'start_date', $this->start_date ?? null);
            $rowEnd   = \Illuminate\Support\Arr::get($row, 'end_date',   $this->end_date   ?? null);

            $items[] = [
                'product_id'     => $productId,
                'variant_id'     => $variantId,
                'qty'            => $qty,
                'unit'           => $unit,
                'price_snapshot' => $snapshot,
                'meta'           => $meta,

                // ✅ per-row cadence we want to save in draft_order_items
                'frequency_type' => $this->normFrequency($rowFreq),
                'start_date'     => $this->castDate($rowStart),
                'end_date'       => $this->castDate($rowEnd),
            ];
        }

        $this->items = array_values($items);
    }

    private function normFrequency(?string $ui): string
    {
        $map = [
            'daily' => 'daily',
            'alternate' => 'alternate_days',
            'alternate_days' => 'alternate_days',
            'weekdays' => 'weekdays',
            'weekends' => 'weekends',
            'saturday' => 'sat',
            'sat' => 'sat',
            'sunday' => 'sun',
            'sun' => 'sun',
            'custom' => 'custom',
            'on_demand' => 'on_demand',
            'ondemand' => 'on_demand',
        ];
        $k = strtolower(trim((string)$ui));
        return $map[$k] ?? 'daily';
    }

    private function castDate(?string $v): ?string
    {
        if (!$v) return null;
        try {
            return \Carbon\Carbon::parse($v)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }


    /* ---------- Contract Save Handler ---------- */

    #[On('contract:save')]
    public function handleContractSave(): void
    {
        $this->normalize(); {

            // 0) Build items from UI rows so validator sees a proper structure
            $this->buildItemsFromRows();
            try {

                $this->validate([
                    'vendor_id'                 => ['required', 'exists:users,id'],
                    'zone_id'                   => ['nullable', 'exists:zones,id'],
                    'customer_id'               => ['nullable', 'exists:users,id'],
                    'subscription_type_id'    => ['required', 'integer', 'exists:subscription_types,id'],
                    'frequency_type'            => ['required', Rule::in(['daily', 'alternate_days', 'weekdays', 'weekends', 'sat', 'sun', 'custom', 'on_demand'])],
                    'invoice_cycle'             => ['required', Rule::in(['monthly', 'weekly', 'custom'])],
                    'start_date'                => ['required', 'date'],
                    'end_date'                  => ['nullable', 'date', 'after_or_equal:start_date'],
                    'change_reason'             => ['required', Rule::in(['self_service', 'user-error', 'staff-error'])],
                    'items'                     => ['required', 'array', 'min:1'],
                    'items.*.product_id'        => ['required', 'integer'],
                    'items.*.variant_id'        => ['required', 'integer'],
                    'items.*.qty'               => ['nullable', 'numeric', 'min:0.01'],
                    'items.*.unit'              => ['nullable', 'string', 'max:16'],
                    'items.*.price_snapshot'    => ['nullable', 'numeric', 'min:0'],
                    // 👇 new
                    'items.*.frequency_type'     => ['required', Rule::in(['daily', 'alternate_days', 'weekdays', 'weekends', 'sat', 'sun', 'custom', 'on_demand'])],
                    'items.*.start_date'         => ['nullable', 'date'],
                    'items.*.end_date'           => ['nullable', 'date', 'after_or_equal:items.*.start_date'],
                    'items.*.meta'          => ['nullable'],
                    // 'items.*.vendor_base_rate'  => ['nullable', 'numeric', 'min:0'],
                    // 'items.*.commission_type'   => ['nullable', Rule::in(['percent', 'fixed'])],
                    // 'items.*.commission_value'  => ['nullable', 'numeric', 'min:0'],
                    // 'items.*.customer_price'    => ['nullable', 'numeric', 'min:0'],
                    // 'items.*.gst_rate'          => ['nullable', 'numeric', 'min:0', 'max:28'],
                    // 'items.*.pack_size'         => ['nullable', 'string', 'max:64'],
                    // 'items.*.cutoff_time'       => ['nullable', 'date_format:H:i:s'],
                    // 'items.*.lead_time_minutes' => ['nullable', 'integer', 'min:0'],
                    // 'items.*.active'            => ['nullable', 'boolean'],
                ]);
            } catch (\Exception $e) {
                $this->dispatch('toast', type: 'error', message: 'An error occurred while saving the contract. Please try again.');
                return;
            }

            //$byUserId  = auth()->id();          // may be null if you allow anonymous
            // $byUserId = session('signup_user_id'); //auth()->check() ? auth()->id() : null;
            // $forUserId = session('signup_user_id'); //$this->vendor_id;      // contract is for the vendor
            $byUserId = session('signup_user_id') ?: Auth::id();  // who performs the change
            $forUserId = $this->vendor_id ?? $byUserId;               // the vendor for whom it applies

            // Guard: if still null, fail early with a friendly toast
            if (empty($byUserId) || empty($forUserId)) {
                $this->dispatch('toast', type: 'error', message: 'Cannot save: user not identified.');
                return;
            }


            try {
                DB::transaction(function () use ($byUserId, $forUserId) {
                    // 2) sub_change_requests — PENDING
                    $scr = SubChangeRequest::create([
                        'for_user_id'              => $forUserId,
                        'by_user_id'               => $byUserId,
                        'from_id'                  => null,
                        // 'order_id'                 => null,
                        'draft_order_id'          => null,
                        'subscription_type_id'     => $this->subscription_type_id,
                        // DB column is subscription_subtype_id (no extra underscore)
                        'subtypes_json'            => null,
                        // 'frequency_type'           => $this->frequency_type,
                        'custom_frequency_format'  => $this->custom_frequency_format,
                        'invoice_cycle'            => $this->invoice_cycle,
                        'change_reason'            => $this->change_reason,
                        // 'start_date'               => $this->start_date,
                        // 'end_date'                 => $this->end_date,
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
                        'meta'                     => null,
                    ]);

                    // 3) draft_orders
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

                    // 4) draft_order_items — upsert against (draft_order_id, variant_id, vendor_id)
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

                                // 👇 TAKE FROM ITEM (not from top-level)
                                'frequency_type' => $this->normFrequency(\Illuminate\Support\Arr::get($it, 'frequency_type')),
                                'start_date'     => $this->castDate(\Illuminate\Support\Arr::get($it, 'start_date')),
                                'end_date'       => $this->castDate(\Illuminate\Support\Arr::get($it, 'end_date')),
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
                                ['draft_order_id', 'variant_id', 'vendor_id'],
                                [
                                    'qty',
                                    'unit',
                                    'price_snapshot',
                                    'meta',
                                    'frequency_type',   // 👈 add
                                    'start_date',       // 👈 add
                                    'end_date',         // 👈 add
                                    // 'vendor_base_rate',
                                    // 'commission_type',
                                    // 'commission_value',
                                    // 'customer_price',
                                    // 'gst_rate',
                                    // 'pack_size',
                                    // 'cutoff_time',
                                    // 'lead_time_minutes',
                                    // 'active',
                                    'updated_at'
                                ]
                            );
                        }
                    }
                });
            } catch (\Throwable $e) {
                // TEMP while debugging:
                report($e);              // logs into laravel.log
                dd($e->getMessage());    // or dump to screen so you see the exact error
                // $this->dispatch('toast', type: 'error', message: 'Could not save the contract.'); // keep if you prefer
                return;
            }

            // success → advance
            $this->dispatch('toast', type: 'success', message: 'Contract saved & sent for approval.');
            $this->dispatch('moveToStep', 3)->to(VendorSignupWizard::class);
        } // normalize
    }

    /** Returns [id => name] from subscription_types (active first) */
    private function loadSubscriptionTypeOptions(): array
    {
        return DB::table('subscription_types')
            ->select('id', 'name')
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    // NEW: load subtypes for a subscription_type_id
    private function loadSubtypesForTypeId(int $typeId): array
    {
        return DB::table('subscription_sub_types')
            ->select('id', 'name', 'slug')
            ->where('subscription_type_id', $typeId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn($r) => ['id' => (int)$r->id, 'slug' => $r->slug, 'label' => $r->name])
            ->all();
    }

    /** Look up type name by id */
    private function lookupTypeName(int $id): ?string
    {
        return DB::table('subscription_types')->where('id', $id)->value('name');
    }

    /**
     * Subtypes from subscription_sub_types for a given type id.
     * Output: [['id'=>..,'key'=>'bakery_other','label'=>'Bakery Other'], ...]
     */
    private function subtypesFromSubscriptionSubTypes(int $typeId): array
    {
        $rows = DB::table('subscription_sub_types')
            ->select('id', 'name', 'slug')
            ->where('subscription_type_id', $typeId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'    => (int) $r->id,
                'key'   => $r->slug ?: $this->slug($r->name),
                'label' => $r->name,
            ];
        }
        return $out;
    }

    // OLD: products-based map (kept)
    private function buildTypeSubtypeMapFromDb(): array
    {
        $rows = DB::table('subscription_types as t')
            ->join('subscription_sub_types as st', 'st.subscription_type_id', '=', 't.id')
            ->select(
                't.id as type_id',
                't.name as type_name',
                'st.id as sub_id',
                'st.name as sub_name',
                'st.slug as sub_slug',
                't.status as t_status',
                'st.status as st_status'
            )
            ->where('t.status', 'active')
            ->where('st.status', 'active')
            ->orderBy('t.name')
            ->orderBy('st.name')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $tid = (int) $r->type_id;
            if (!isset($map[$tid])) {
                $map[$tid] = [
                    'id'       => $tid,
                    'label'    => $r->type_name,
                    'subtypes' => [],
                ];
            }
            $map[$tid]['subtypes'][] = [
                'id'    => (int) $r->sub_id,
                'key'   => $r->sub_slug ?: $this->slug($r->sub_name),
                'label' => $r->sub_name,
            ];
        }
        return $map;
    }
    /** Vendor dropdown options built from DB types (alpha). */
    // private function vendorTypeOptionsFromDb(): array
    // {
    //     // Show pretty = raw label as both key & value

    //     if (empty($this->typeToSubtypes)) {
    //         // list is empty.
    //         $this->typeToSubtypes = $this->buildTypeSubtypeMapFromDb();
    //     }

    //     $types = array_keys($this->typeToSubtypes);
    //     sort($types, SORT_NATURAL | SORT_FLAG_CASE);

    //     $out = [];
    //     foreach ($types as $label) {
    //         $out[$label] = $label; // value => label
    //     }
    //     return $out;
    // }

    /**
     * Build subtypes array for a given type label from DB map.
     * Output format matches your Blade: [['key' => 'milk', 'label' => 'Milk'], ...]
     */
    private function subtypesForTypeFromDb(string $typeLabel): array
    {
        $subs = $this->typeToSubtypes[$typeLabel] ?? [];
        return array_map(fn($label) => [
            'key'   => $this->slug($label),
            'label' => $label,
        ], $subs);
    }

    private function slug(string $s): string
    {
        return preg_replace('/\s+/', '_', strtolower(trim($s)));
    }

    // =========================
    // ADDED: ID-based map built from subscription_* tables (joins)
    // =========================
    private function buildTypeSubtypeMapFromDbJoins(): array
    {
        $rows = DB::table('subscription_types as t')
            ->join('subscription_sub_types as st', 'st.subscription_type_id', '=', 't.id')
            ->select(
                't.id as type_id',
                't.name as type_name',
                'st.id as sub_id',
                'st.name as sub_name',
                'st.slug as sub_slug',
                't.status as t_status',
                'st.status as st_status'
            )
            ->where('t.status', 'active')
            ->where('st.status', 'active')
            ->orderBy('t.name')
            ->orderBy('st.name')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $tid = (int) $r->type_id;
            if (!isset($map[$tid])) {
                $map[$tid] = [
                    'id'       => $tid,
                    'label'    => $r->type_name,
                    'subtypes' => [],
                ];
            }
            $map[$tid]['subtypes'][] = [
                'id'    => (int) $r->sub_id,
                'key'   => $r->sub_slug ?: $this->slug($r->sub_name),
                'label' => $r->sub_name,
            ];
        }
        return $map;
    }

    public function render()
    {
        return view('livewire.signup.vendor-contract-details', [
            // Keep your Blade intact; it loops over $subscriptionTypeOptions for the select
            'subtypes'       => $this->subtypes,
            'zoneId'         => $this->zoneId,
            'vendorId'       => $this->vendorId,
            'typeOptions'    => $this->subscriptionTypeOptions, // if your Blade uses $typeOptions
            'subscriptionTypeOptions' => $this->subscriptionTypeOptions, // if it uses the old name
        ]);
    }
}
