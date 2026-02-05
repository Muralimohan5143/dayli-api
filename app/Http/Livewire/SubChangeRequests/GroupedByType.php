<?php

namespace App\Http\Livewire\SubChangeRequests;

use Livewire\Component;
use App\Models\SubChangeRequest;
use Livewire\Attributes\On;             // ✅ add this
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use Carbon\Carbon;

class GroupedByType extends Component
{
    public string $search = '';

    public $zoneId = 1;
    public $vendorId = null;
    public $customerId    = null;

    public $showCreateModal = false;
    public $createTypeName = null;
    public $modalSubtypeGroups = [];
    // subscription info
    public $subscription_type_id        = null;
    public $subscription_subtype_id     = null;
    public $subscriptionType            = null; // human label e.g. "Bakery & Snacks"
    public $subscriptionSubtype         = null; // human label e.g. "Bread & Buns"


    // cadence / billing
    public $frequency_type              = null;
    public $custom_frequency_format     = null;
    public $invoice_cycle               = null;
    public $change_reason = 'self_service';
    public $start_date                  = null;
    public $end_date                    = null;

    public $debugHit = 'not clicked';


    public bool $showDeactivateConfirm = false;
    public ?int $targetTypeId = null;
    public ?string $targetTypeName = null;

    // === EDIT MODAL STATE ===
    public bool $showEditModal = false;
    public ?int $editProductId = null;
    public string $editProductTitle = '';
    public float $editQty = 0;
    public string $editUnit = 'pcs';

    // 🧩 ADD THIS MISSING LINE
    public array $editCrIds = [];   // holds all change_request IDs for this group

    // new fields
    public string $editFrequencyType = 'daily';
    public ?string $editStartDate = null;
    public ?string $editEndDate = null;

    // state
    public ?int $sourceChangeRequestId = null;

    // Open the modal
    public function openEdit(
        int $productId,
        array $crIds,
        string $title,
        float $qty = 0,
        string $unit = 'pcs'
    ): void {
        $this->editProductId = $productId;
        $this->editCrIds = $crIds;          // store them
        $this->editProductTitle = $title;
        $this->editQty = $qty;
        $this->editUnit = $unit;

        // Defaults for frequency & dates
        $this->editFrequencyType = 'daily';
        $this->editStartDate = now()->toDateString();
        $this->editEndDate = null;

        $this->showEditModal = true;
    }

    // Close the modal
    public function cancelEdit(): void
    {
        $this->reset(['showEditModal', 'editProductId', 'editCrIds', 'editProductTitle', 'editQty', 'editUnit']);
    }

    // Save edits: update draft_order_items for the draft_orders that belong to these CRs
    public function saveEdit(): void
    {
        if (!$this->editProductId || empty($this->editCrIds)) {
            $this->dispatch('toast', type: 'error', msg: 'No records selected to edit.');
            return;
        }
        // ✅ Normalize to enum-safe values before saving
        $map = [
            'daily' => 'daily',
            'alternate' => 'alternate_days',
            'alternate_days' => 'alternate_days',
            'weekdays' => 'weekdays',
            'weekends' => 'weekends',
            'saturday' => 'sat',
            'sunday' => 'sun',
            'sun' => 'sun',
            'custom' => 'custom',
            'on_demand' => 'on_demand',
            'ondemand' => 'on_demand',
        ];

        $this->editFrequencyType = strtolower(trim($this->editFrequencyType));
        $this->editFrequencyType = $map[$this->editFrequencyType] ?? 'daily';

        DB::transaction(function () {


            $start = $this->sanitizeFutureDate($this->editStartDate, 'start');
            $end   = $this->sanitizeFutureDate($this->editEndDate, 'end', $start ? \Carbon\Carbon::parse($start) : null);
            // 1) Draft orders linked to these change requests
            $draftIds = DB::table('draft_orders')
                ->whereIn('change_request_id', $this->editCrIds)
                ->pluck('id');

            // 2) If a product was selected, update its qty/unit in those draft orders
            if ($this->editProductId && $draftIds->isNotEmpty()) {

                DB::table('draft_order_items')
                    ->whereIn('draft_order_id', $draftIds)
                    ->where('product_id', $this->editProductId)
                    ->update([
                        'qty'            => $this->editQty,
                        'unit'           => $this->editUnit,
                        'frequency_type' => $this->editFrequencyType,   // <-- per-item cadence now
                        'start_date'     => $start,
                        'end_date'       => $end,
                        'updated_at'     => now(),
                    ]);
            }

            // 3) Update cadence/dates in parent draft orders (optional but recommended)
            if ($draftIds->isNotEmpty()) {
                DB::table('draft_orders')
                    ->whereIn('id', $draftIds)
                    ->update([
                        'start_date'     => $start,
                        'end_date'       => $end,
                        'updated_at'     => now(),
                    ]);
            }
        });

        $this->cancelEdit();
        $this->dispatch('toast', type: 'success', msg: 'Updated successfully.');
        $this->dispatch('$refresh');
    }
    // let children tell this component to refresh
    protected $listeners = [
        'refreshGroupView' => 'reloadData',
        'variantsSelected' => 'setSelectedItems',
    ];
    public $items = [];
    public function askDeactivateType($typeId, $typeName): void
    {
        $this->targetTypeId = (int)$typeId;
        $this->targetTypeName = $typeName;
        $this->showDeactivateConfirm = true;
    }

    public function cancelDeactivate(): void
    {
        $this->reset(['showDeactivateConfirm', 'targetTypeId', 'targetTypeName']);
    }

    public function confirmDeactivate(?int $typeId = null, ?string $typeName = null): void
    {
        if ($typeId)   $this->targetTypeId   = $typeId;
        if ($typeName) $this->targetTypeName = $typeName;

        if (!$this->targetTypeId) {
            $this->dispatch('toast', type: 'error', msg: 'No typeId.');
            return;
        }
        // Reproduce the same visibility filter used in render()
        $visibleCrIds = SubChangeRequest::query()
            ->visibleTo(Auth::id())
            ->pluck('id');
        // All SCRs currently powering that block (e.g., "Flowers")
        $scrIds = DB::table('sub_change_requests as scr')
            ->whereIn('scr.id', $visibleCrIds)
            ->where('scr.subscription_type_id', $this->targetTypeId)
            ->pluck('scr.id');
        if ($scrIds->isEmpty()) {
            $this->dispatch('toast', type: 'warning', msg: 'Nothing to delete for this type (in current view).');
            $this->cancelDeactivate();
            return;
        }
        DB::transaction(function () use ($scrIds) {
            // Delete children → parents (works even without FK cascades)
            $draftIds = DB::table('draft_orders')
                ->whereIn('change_request_id', $scrIds)
                ->pluck('id');
            if ($draftIds->isNotEmpty()) {
                DB::table('draft_order_items')->whereIn('draft_order_id', $draftIds)->delete();
                DB::table('draft_orders')->whereIn('id', $draftIds)->delete();
            }
            DB::table('sub_change_requests')->whereIn('id', $scrIds)->delete();
        });

        $this->cancelDeactivate();
        $this->dispatch('toast', type: 'success', msg: 'Deleted all requests for this type from the current view.');
        $this->dispatch('$refresh'); // Livewire v3
    }

    public function setSelectedItems($payload)
    {
        $this->items = $payload['items'] ?? [];
        // temp debug toast to prove data arrived
        $this->dispatch('cr-error', message: json_encode($this->items));
    }
    public function mount()
    {
        // vendor fallback = logged-in user
        if ($this->vendorId === null) {
            $this->vendorId = Auth::id();
        }

        // TEMP: you must decide who the "customer" is for this request.
        // For now fallback same as vendor/logged in so we don't get null DB
        if ($this->customerId === null) {
            $this->customerId = Auth::id();
        }

        // TEMP defaults for testing so saveRequestNow() won't die with nulls:
        if ($this->subscription_type_id === null) {
            $this->subscription_type_id = 1;
        }
        if ($this->subscription_subtype_id === null) {
            $this->subscription_subtype_id = 1;
        }
        if ($this->subscriptionType === null) {
            $this->subscriptionType = 'Test Type';
        }
        if ($this->subscriptionSubtype === null) {
            $this->subscriptionSubtype = 'Test Subtype';
        }
        if ($this->frequency_type === null) {
            $this->frequency_type = 'daily';
        }
        if ($this->invoice_cycle === null) {
            $this->invoice_cycle = 'monthly';
        }
        if ($this->start_date === null) {
            $this->start_date = Carbon::now()->toDateString();
        }

        // 🔥 TEMP DUMMY PRODUCT LINE so button works immediately:
        $this->reloadData();
    }

    #[On('refreshGroupView')]
    public function reloadData(): void
    {
        // make sure this actually rebuilds $this->groups
        $this->dispatch('$refresh');
    }

    // optional helper (fallback if UI didn’t pass an id)
    private function latestScrIdFor(int $forUserId, int $typeId): ?int
    {
        return DB::table('sub_change_requests')
            ->where('for_user_id', $forUserId)
            ->where('subscription_type_id', $typeId)
            ->whereIn('status', ['pending', 'active'])
            ->orderByDesc('id')
            ->value('id');
    }
    public function openCreateModal($typeId, $typeName, ?int $fromId = null)
    {
        // these will be used by saveRequestNow()
        $this->subscription_type_id = $typeId;
        $this->subscriptionType     = $typeName;

        // 👇 capture the source SCR id
        $this->sourceChangeRequestId = $fromId;

        $this->items = []; // 🔥 reset previous selection
        $this->createTypeName = $typeName;
        $this->showCreateModal = true;
        $this->debugHit = 'clicked';

        $zoneId = $this->zoneId;

        // 1. pull distinct subtype names for this type & zone
        $rawSubtypes = DB::table('zone_product_variants as zpv')
            ->join('products as p', 'p.product_id', '=', 'zpv.product_id')
            ->where('zpv.zone_id', $zoneId)
            ->whereRaw('LOWER(p.product_type) = ?', [mb_strtolower($typeName)])
            ->distinct()
            ->pluck('p.product_sub_type'); // ["Biscuits And Chips", "Bread And Buns", ...]

        // 2. join against subscription_sub_types to get id + slug if possible
        $subRows = DB::table('subscription_sub_types as sst')
            ->select('sst.id', 'sst.name', 'sst.slug')
            ->whereIn(DB::raw('LOWER(sst.name)'), $rawSubtypes->map(fn($n) => mb_strtolower($n))->filter())
            ->get();

        $sstByLowerName = $subRows->keyBy(function ($row) {
            return mb_strtolower($row->name);
        });

        // 3. final shape
        $this->modalSubtypeGroups = $rawSubtypes
            ->filter()
            ->unique(function ($label) {
                return mb_strtolower($label);
            })
            ->map(function ($label) use ($sstByLowerName) {
                $lower = mb_strtolower($label);
                $match = $sstByLowerName[$lower] ?? null;

                return [
                    'subtype_label' => $label,                            // "Biscuits And Chips"
                    'subtype_slug'  => $match->slug ?? $this->slugify($label), // "biscuits_and_chips"
                    'subtype_id'    => $match->id   ?? null,              // numeric id if match, else null
                ];
            })
            ->values()
            ->toArray();
    }

    private function slugify(string $s): string
    {
        $s = trim(mb_strtolower($s));
        $s = preg_replace('/\s+/', '_', $s);
        return $s ?: '';
    }
    public function closeCreateModal()
    {
        $this->showCreateModal = false;
    }

    public function updatingSearch()
    {
        // no-op for now
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


    private function sanitizeFutureDate($v, string $type = 'start', ?\Carbon\Carbon $start = null): ?string
    {
        if (empty($v)) return null;
        try {
            $d = \Carbon\Carbon::parse($v)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        $today = \Carbon\Carbon::today();

        // must be today or future
        if ($d->lt($today)) return $today->toDateString();

        // end must be >= start if provided
        if ($type === 'end' && $start && $d->lt($start)) {
            return $start->toDateString();
        }
        return $d->toDateString();
    }
    // private function castDate($v): ?string
    // {
    //     if (!$v) return null;
    //     try {
    //         return Carbon::parse($v)->toDateString();
    //     } catch (\Throwable $e) {
    //         return null;
    //     }
    // }


    private function resolveSourceScrId(int $forUserId, int $typeId, ?int $explicit = null, ?int $productId = null): ?int
    {
        if ($explicit) return (int) $explicit;

        // Prefer latest by (customer, type) with no joins (works even if no draft exists yet)
        $latest = $this->latestScrIdFor($forUserId, $typeId);
        if ($latest) return (int) $latest;

        // As a last resort, allow product-specific match BUT do not require items to exist yet
        if ($productId) {
            $scr = DB::table('sub_change_requests as scr')
                ->leftJoin('draft_orders as do', 'do.change_request_id', '=', 'scr.id')
                ->leftJoin('draft_order_items as doi', 'doi.draft_order_id', '=', 'do.id')
                ->where('scr.for_user_id', $forUserId)
                ->where('scr.subscription_type_id', $typeId)
                ->where(function ($q) use ($productId) {
                    $q->whereNull('doi.product_id')->orWhere('doi.product_id', $productId);
                })
                ->orderByDesc('scr.id')
                ->value('scr.id');

            if ($scr) return (int) $scr;
        }

        return null;
    }
    public function saveRequestNow()
    {
        // safety check: we need at least 1 line item
        if (empty($this->items) || count($this->items) === 0) {
            $this->dispatch('cr-error', message: 'No products to save.');
            return;
        }

        $byUserId  = session('signup_user_id') ?: Auth::id(); // who is operating
        $forUserId = $this->customerId;                       // target customer
        $typeId    = (int) $this->subscription_type_id;

        // Resolve ONE source SCR for this batch:
        // Prefer explicit $this->sourceChangeRequestId; else try the first product's SCR; else latest for (customer,type).
        $firstProductId = (int) Arr::get($this->items[0] ?? [], 'product_id');
        $scrId = $this->resolveSourceScrId(
            forUserId: $forUserId,
            typeId: $typeId,
            explicit: $this->sourceChangeRequestId,
            productId: $firstProductId ?: null
        );

        if (!$scrId) {
            // Strict per your ask: do NOT create a new SCR; fail visibly if none exists.
            $this->dispatch('cr-error', message: 'No existing Change Request found to attach. Open via Contract Details first.');
            return;
        }

        try {
            DB::transaction(function () use ($scrId, $byUserId, $typeId) {

                // Normalize parent dates from component-level start/end
                $parentStart = $this->sanitizeFutureDate($this->start_date ?: \Carbon\Carbon::today()->toDateString(), 'start');
                $parentEnd   = $this->sanitizeFutureDate($this->end_date, 'end', $parentStart ? \Carbon\Carbon::parse($parentStart) : null);

                // Find an OPEN draft order for this SCR; else create one
                $draft = DraftOrder::query()
                    ->where('change_request_id', $scrId)
                    ->whereIn('status', ['open', 'active'])   // keep both if you use both
                    ->latest('id')
                    ->first();

                if (!$draft) {
                    $payload = [
                        'change_request_id'        => $scrId,
                        'customer_id'              => $this->customerId,
                        'vendor_id'                => $this->vendorId,
                        'zone_id'                  => $this->zoneId,
                        // 'subscription_type_id'     => $this->subscription_type_id,
                        // 'subscription_subtype_id'  => $this->subscription_subtype_id,
                        'cadence'                  => $this->frequency_type,
                        'custom_frequency_format'  => $this->custom_frequency_format,
                        'invoice_cycle'            => $this->invoice_cycle,
                        'start_date'               => $parentStart,
                        'end_date'                 => $parentEnd,
                        'status'                   => 'active',
                        'locked_at'                => null,
                        'timezone'                 => 'Asia/Kolkata',
                        'title'                    => 'Vendor Contract Draft',
                        'pricing_policy'           => null,
                        'tax_policy'               => null,
                        'meta'                     => null,

                    ];

                    try {
                        $draft = DraftOrder::create($payload);
                    } catch (\Throwable $massAssign) {
                        // Fallback if $fillable/guarded blocks create()
                        $now = now();
                        $payload['created_at'] = $now;
                        $payload['updated_at'] = $now;
                        $id = DB::table('draft_orders')->insertGetId($payload);
                        $draft = DraftOrder::find($id);
                    }
                } else {
                    // keep draft but ensure dates are normalized if you want to refresh them
                    $draft->update([
                        'start_date' => $parentStart,
                        'end_date'   => $parentEnd,
                    ]);
                }

                // Upsert items into this draft
                if (!empty($this->items)) {
                    $now  = now();
                    $rows = [];



                    foreach ($this->items as $it) {
                        $rawStart = Arr::get($it, 'start_date', \Carbon\Carbon::today()->toDateString());
                        $start    = $this->sanitizeFutureDate($rawStart, 'start');

                        $rawEnd   = Arr::get($it, 'end_date');
                        $end      = $this->sanitizeFutureDate($rawEnd, 'end', $start ? \Carbon\Carbon::parse($start) : null);

                        $rows[] = [
                            'draft_order_id'    => $draft->id,
                            'product_id'        => Arr::get($it, 'product_id'),
                            'variant_id'        => Arr::get($it, 'variant_id'),
                            'frequency_type'    => $this->normFrequency(Arr::get($it, 'frequency_type', 'daily')),
                            'vendor_id'         => $this->vendorId,
                            'qty'               => Arr::get($it, 'qty', 1.00),
                            'unit'              => Arr::get($it, 'unit', 'pcs'),
                            'start_date'     => $start,
                            'end_date'       => $end,
                            'created_at'        => $now,
                            'updated_at'        => $now,
                        ];
                    }

                    if (!empty($rows)) {
                        // NOTE: your current unique keys are ['draft_order_id','variant_id','vendor_id'].
                        // If variant_id can be null or you want product-level merge, consider switching to
                        // ['draft_order_id','product_id','vendor_id'] in the DB unique index.
                        DraftOrderItem::upsert(
                            $rows,
                            ['draft_order_id', 'product_id', 'vendor_id'],
                            [
                                'frequency_type',
                                'qty',
                                'unit',
                                'start_date',
                                'end_date',
                                'price_snapshot',
                                'meta',
                                'updated_at',
                            ]
                        );
                    }
                }
            });

            // after commit: refresh UI and toast
            $this->reloadData();
            $this->showCreateModal = false; // close modal
            $this->dispatch('cr-saved', message: 'Draft updated from existing request.');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('cr-error', message: 'Save failed. ' . $e->getMessage());
        }
    }


    public function render()
    {
        // main page data (unchanged from what you had before
        $visibleCrIds = SubChangeRequest::query()
            ->visibleTo(Auth::id())
            ->pluck('id');
        $rows = DB::table('sub_change_requests as scr')
            ->leftJoin('subscription_types as st', 'st.id', '=', 'scr.subscription_type_id')
            ->join('draft_orders as do', 'do.change_request_id', '=', 'scr.id')
            ->join('draft_order_items as doi', 'doi.draft_order_id', '=', 'do.id')
            ->join('products as p', 'p.product_id', '=', 'doi.product_id')
            ->whereIn('scr.id', $visibleCrIds)
            ->when(trim($this->search) !== '', function ($q) {
                $s = '%' . trim($this->search) . '%';
                $q->where(function ($w) use ($s) {
                    $w->where('st.name', 'like', $s)
                        ->orWhere('p.title', 'like', $s);
                });
            })
            ->select([
                'scr.id as cr_id',
                'scr.subscription_type_id',
                'st.name as type_name',
                'p.product_id',
                'p.title as product_title',
                'doi.qty',
                'doi.unit',
            ])
            ->orderBy('st.name')
            ->orderBy('p.title')
            ->get();
        $groups = $rows->groupBy('subscription_type_id')->map(function ($group, $typeId) {
            $typeName = $group->first()->type_name ?? 'Unknown Type';
            $products = $group->groupBy('product_id')->map(function ($items) {
                return [
                    'product_id' => $items->first()->product_id,
                    'title'      => $items->first()->product_title,
                    'unit'       => $items->first()->unit,
                    'qty_sum'    => collect($items)->sum('qty'),
                    'cr_ids'     => $items->pluck('cr_id')->unique()->values(),
                ];
            })->values();

            return [
                'type_id'    => $typeId,        // <-- NEW
                'type_name' => $typeName,
                'total_qty' => $products->sum('qty_sum'),
                'products'  => $products,
            ];
        })->values();

        if ($this->debugHit === 'not clicked' && $this->showCreateModal !== false) {
            $this->showCreateModal = false;
        }

        return view('livewire.sub-change-requests.grouped-by-type', [
            'groups'          => $groups,
            'zoneId'          => $this->zoneId,
            'vendorId'        => $this->vendorId,
            'createTypeName'  => $this->createTypeName,
            'showCreateModal' => $this->showCreateModal,
            'debugHit'        => $this->debugHit,

            // 👇 expose modal subtype grouping to blade
            'modalSubtypeGroups' => $this->modalSubtypeGroups,
        ]);
    }
}
