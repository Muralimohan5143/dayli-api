<?php

namespace App\Http\Livewire\Signup;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Illuminate\Support\Str;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Schema;

class ZoneVariantsList extends Component
{
    use WithPagination;

    protected string $paginatorName = 'page';

    // Incoming props (now can be passed as either names or ids)
    public string $category = '';
    public string $subtype  = '';
    public ?int   $typeId   = null;   // ✅ optional
    public ?int   $subtypeId = null;   // ✅ optional
    public int    $zoneId   = 1;
    public ?int   $vendorId = null;


    public ?string $defaultFrequency = null;      // NEW
    public ?string $defaultStart = null;          // NEW
    public ?string $defaultEnd = null;            // NEW

    // ⬇️ add this
    public ?int $draftOrderId = null;

    #[Url(as: 'q', keep: true)]
    public string $q = '';

    public bool $onlyActive = false;

    public string $context = 'contract'; // default for old VendorContractDetails flow

    /**
     * Row bag keyed by variant_id.
     */

    public array $rows = [];

    protected $paginationTheme = 'bootstrap';
    public int $perPage = 15;

    public function mount(
        string $category = '',
        string $subtype = '',
        int $zoneId = 1,
        ?int $vendorId = null,
        ?int $typeId = null,
        ?int $subtypeId = null,
        ?int $draftOrderId = null,       // 👈 added
        ?string $defaultFrequency = null,         // NEW
        ?string $defaultStart = null,             // NEW
        ?string $defaultEnd = null,               // NEW
        string $context = 'contract'              // NEW
    ): void {
        $this->category  = $category;
        $this->subtype   = $subtype;
        $this->zoneId    = $zoneId ?: 1;
        $this->vendorId  = $vendorId;
        $this->typeId    = $typeId;
        $this->subtypeId = $subtypeId;
        $this->draftOrderId = $draftOrderId;   // 👈 set it
        $this->defaultFrequency = $defaultFrequency ?: 'daily';
        $this->defaultStart     = $defaultStart ?: now()->toDateString();
        $this->defaultEnd       = $defaultEnd;
        $this->context          = $context;

        // 🔎 If IDs are available, resolve canonical labels/slug from subscription tables
        if ($this->typeId && $this->category === '') {
            $this->category = (string) DB::table('subscription_types')
                ->where('id', $this->typeId)
                ->value('name') ?? '';
        }
        if ($this->subtypeId && $this->subtype === '') {
            $row = DB::table('subscription_sub_types')
                ->select('slug', 'name')
                ->where('id', $this->subtypeId)
                ->first();
            if ($row) {
                $this->subtype = $row->slug ?: (string) $row->name;
            }
        }

        $this->paginatorName = 'p_' . Str::slug($this->subtype ?: 'sub', '_') . '_z' . $this->zoneId;
    }


    public function pushSelectionToParent()
    {
        // Take only checked rows
        $selected = [];

        foreach ($this->rows as $row) {
            if (!empty($row['checked'])) {
                $selected[] = [
                    'product_id' => $row['product_id'],
                    'variant_id' => $row['variant_id'],
                    'qty'        => $row['qty'] ?? 1,
                    'unit'       => $row['unit'] ?? 'pcs',
                ];
            }
        }

        // Emit up to the parent component
        $this->dispatch('variantsSelected', $selected)
            ->to(\App\Http\Livewire\SubChangeRequests\GroupedByType::class);
    }
    public function pushSelectionUp(): void
    {
        $items = [];

        foreach ($this->rows as $vid => $row) {
            if (empty($row['selected'])) continue;

            $items[] = [
                'variant_id'        => (int) $vid,
                'product_id'        => (int) ($row['product_id'] ?? 0),
                'qty'               => (float) ($row['qty'] ?? 1.00),
                'unit'              => (string) ($row['unit'] ?? 'pcs'),
                'price_snapshot'    => $row['price_snapshot'] ?? null,
                'meta'              => $row['meta'] ?? null,
                'vendor_base_rate'  => $row['vendor_base_rate'] ?? null,
                'commission_type'   => $row['commission_type'] ?? 'percent',
                'commission_value'  => $row['commission_value'] ?? 0,
                'customer_price'    => $row['customer_price'] ?? null,
                'gst_rate'          => $row['gst_rate'] ?? null,
                'pack_size'         => $row['pack_size'] ?? null,
                'cutoff_time'       => $row['cutoff_time'] ?? null,
                'lead_time_minutes' => (int) ($row['lead_time_minutes'] ?? 0),
                'active'            => (bool) ($row['active'] ?? true),

                // 👇 ADD THESE THREE KEYS (from each row)
                'frequency_type'    => $row['frequency_type'] ?? null,
                'start_date'        => $row['start_date'] ?? null,
                'end_date'          => $row['end_date'] ?? null,
            ];
        }

        if ($this->context === 'sub-change') {
            $this->dispatch('variantsSelected', [
                'subtype_id' => $this->subtypeId,
                'items'      => $items,
            ])->to(\App\Http\Livewire\SubChangeRequests\GroupedByType::class);
        } else {
            $this->dispatch('contract:items-changed', [
                'subtype_id' => $this->subtypeId,
                'items'      => $items,
            ])->to(\App\Http\Livewire\Signup\VendorContractDetails::class);
        }
    }
    public function getPageName(): string
    {
        return $this->paginatorName;
    }

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function toggleOnlyActive(): void
    {
        $this->onlyActive = !$this->onlyActive;
        $this->resetPage($this->paginatorName);
    }

    public function setMode(int $variantId, string $mode): void
    {
        if (!in_array($mode, ['percent', 'amount'], true)) return;

        $this->ensureRow($variantId);
        $this->rows[$variantId]['mode'] = $mode;

        if ($mode === 'percent') $this->rows[$variantId]['amount'] = 0.0;
        if ($mode === 'amount')  $this->rows[$variantId]['percent'] = 0.0;

        $this->recalcRow($variantId);
    }

    public function updatePercent(int $variantId): void
    {
        $this->ensureRow($variantId);
        $p = (float) ($this->rows[$variantId]['percent'] ?? 0);
        $this->rows[$variantId]['percent'] = max(0, min(100, $p));
        $this->recalcRow($variantId);
    }

    public function updateAmount(int $variantId): void
    {
        $this->ensureRow($variantId);
        $a = (float) ($this->rows[$variantId]['amount'] ?? 0);
        $this->rows[$variantId]['amount'] = max(0, $a);
        $this->recalcRow($variantId);
    }

    public function updateMrp(int $variantId): void
    {
        $this->ensureRow($variantId);
        $m = (float) ($this->rows[$variantId]['mrp'] ?? 0);
        $this->rows[$variantId]['mrp'] = max(0, $m);
        $this->recalcRow($variantId);
    }

    private function ensureRow(int $variantId): void
    {
        if (!isset($this->rows[$variantId])) {
            $this->rows[$variantId] = [
                'product_type'   => '',
                'product'        => '',
                'title'          => '',
                'sku'            => '',
                'mrp'            => 0.0,
                'mode'           => 'percent',
                'percent'        => 0.0,
                'amount'         => 0.0,
                'cost'           => 0.0,
                'active_in_zone' => false,
                'selected'       => false,
            ];
        }
    }

    private function recalcRow(int $variantId): void
    {
        $r   = $this->rows[$variantId];
        $mrp = max(0.0, (float) ($r['mrp'] ?? 0));
        $mode = $r['mode'] ?? 'percent';

        if ($mode === 'percent') {
            $pct = max(0.0, min(100.0, (float) ($r['percent'] ?? 0)));
            $this->rows[$variantId]['percent'] = $pct;
            $this->rows[$variantId]['amount']  = round($mrp * $pct / 100, 2);
        } else {
            $amt = max(0.0, min($mrp, (float) ($r['amount'] ?? 0)));
            $this->rows[$variantId]['amount']  = $amt;
            $this->rows[$variantId]['percent'] = $mrp > 0 ? round(($amt / $mrp) * 100, 2) : 0.0;
        }

        $this->rows[$variantId]['cost'] = round($mrp - $this->rows[$variantId]['amount'], 2);
    }

    /** Convert "Bakery Other" → "bakery_other" */
    private function normalizeKey(string $s): string
    {
        $s = trim(mb_strtolower($s));
        $s = preg_replace('/\s+/', '_', $s);
        return $s ?? '';
    }

    /**
     * Base query for TYPE + SUBTYPE + zone.
     */
    private function baseQuery()
    {
        $typeRaw = trim((string) $this->category);
        $subRaw  = trim((string) $this->subtype);

        if ($typeRaw === '' || $subRaw === '') {
            return DB::table('variants as v')->whereRaw('1 = 0');
        }

        // ✅ normalize subtype so it matches DB values like "bakery_other"
        $typeLc = mb_strtolower($typeRaw);
        $subLc  = mb_strtolower($subRaw);
        $subKey = $this->normalizeKey($subRaw); // "Bakery Other" -> "bakery_other"

        $query = DB::table('variants as v')
            ->join('products as p', 'p.product_id', '=', 'v.product_id')
            ->leftJoin('zone_product_variants as zpv', function ($j) {
                $j->on('zpv.product_id', '=', 'v.product_id')
                    ->on('zpv.variant_id', '=', 'v.variant_id')
                    ->where('zpv.zone_id', '=', $this->zoneId);
            })
            ->whereRaw('LOWER(COALESCE(p.product_type, "")) = ?', [$typeLc])
            // match either the raw-lowercased text or the normalized underscore key
            ->where(function ($w) use ($subLc, $subKey) {
                $w->whereRaw('LOWER(COALESCE(p.product_sub_type, "")) = ?', [$subKey])
                    ->orWhereRaw('LOWER(COALESCE(p.product_sub_type, "")) = ?', [$subLc]);
            });

        if ($this->q !== '') {
            $q = '%' . mb_strtolower($this->q) . '%';
            $query->whereRaw('LOWER(p.title) LIKE ?', [$q]);
        }

        if ($this->onlyActive) {
            $query->where('zpv.is_active', 1);
        }


        // Prefer a zone-level price when available, else fallback to variants.price
        $mrpExpr = 'v.price'; // default
        if (Schema::hasColumn('zone_product_variants', 'customer_price')) {
            $mrpExpr = 'COALESCE(zpv.customer_price, v.price)';
        } elseif (Schema::hasColumn('zone_product_variants', 'price')) {
            $mrpExpr = 'COALESCE(zpv.price, v.price)';
        }
        return $query->select([
            'p.product_type',
            'p.title as product_title',
            'v.variant_id',
            'v.product_id',
            'v.title as variant_title',
            'v.sku',
            DB::raw($mrpExpr . ' AS mrp_value'),         // 👈 use a distinct alias
            DB::raw('COALESCE(zpv.is_active, 0) as in_zone'),
        ])->orderBy('p.title')->orderBy('v.position');
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
        $key = strtolower(trim((string) $ui));
        return $map[$key] ?? 'daily';
    }

    protected function syncPageRows(array $items): void
    {
        if (empty($items)) return;

        // If you already have a draft order context, keep it; if not, this can stay null
        $variantIds = array_map(fn($r) => (int) $r->variant_id, $items);

        $byVariant = collect($items)->keyBy(fn($r) => (int) $r->variant_id);

        // Pull existing cadence from draft_order_items (if you have a draft order id)
        $existing = [];
        if ($this->draftOrderId) {
            $existing = DB::table('draft_order_items')
                ->where('draft_order_id', $this->draftOrderId)
                ->whereIn('variant_id', $variantIds)
                ->get()->keyBy('variant_id');
        }

        foreach ($byVariant as $vid => $row) {
            $e = $existing[$vid] ?? null;

            // keep all your old keys in $this->rows[...] — we only add these 3
            $this->rows[$vid]['product_id']     = (int) $row->product_id;
            $this->rows[$vid]['variant_id']     = (int) $row->variant_id;
            $this->rows[$vid]['vendor_id']      = (int) ($row->vendor_id ?? $this->vendorId ?? 0);

            // already-existing fields you had:
            $this->rows[$vid]['qty']            = $this->rows[$vid]['qty'] ?? (float) ($e->qty ?? 1.00);
            $this->rows[$vid]['unit']           = $this->rows[$vid]['unit'] ?? (string) ($e->unit ?? 'pcs');
            $this->rows[$vid]['price_snapshot'] = $this->rows[$vid]['price_snapshot'] ?? $e->price_snapshot ?? null;


            // ✅ Set MRP from query (prefer alias), but don't override if user already edited it
            $mrpFromDb = (float) ($row->mrp_value ?? $row->mrp ?? $row->price ?? 0);
            if (!isset($this->rows[$vid]['mrp']) || (float) $this->rows[$vid]['mrp'] <= 0) {
                $this->rows[$vid]['mrp'] = $mrpFromDb;
            }

            // 👇 NEW — cadence on DOI
            $this->rows[$vid]['frequency_type'] = $this->rows[$vid]['frequency_type']
                ?? (string) ($e->frequency_type ?? ($this->defaultFrequency ?? 'daily'));
            $this->rows[$vid]['start_date'] = $this->rows[$vid]['start_date']
                ?? ($e->start_date ?? ($this->defaultStart ?? now()->toDateString()));
            $this->rows[$vid]['end_date'] = $this->rows[$vid]['end_date']
                ?? ($e->end_date ?? ($this->defaultEnd ?? null));
        }
    }


    public function updateFrequency(int $variantId): void
    {
        $ft = $this->rows[$variantId]['frequency_type'] ?? 'daily';
        $this->rows[$variantId]['frequency_type'] = $this->normFrequency($ft);
    }

    public function saveRow(int $variantId): void
    {
        $row = $this->rows[$variantId] ?? null;
        if (!$row) return;

        if (!$this->draftOrderId) {
            $this->dispatch('toast', type: 'error', msg: 'No draft order context set.');
            return;
        }

        $payload = [
            'draft_order_id' => (int) $this->draftOrderId,
            'product_id'     => (int) $row['product_id'],
            'variant_id'     => (int) $row['variant_id'],
            'vendor_id'      => (int) ($row['vendor_id'] ?? 0),
            'qty'            => (float) ($row['qty'] ?? 1.00),
            'unit'           => (string) ($row['unit'] ?? 'pcs'),
            'price_snapshot' => $row['price_snapshot'] ?? null,

            // 👇 write cadence on DOI (NOT on SCR)
            'frequency_type' => $this->normFrequency($row['frequency_type'] ?? 'daily'),
            'start_date'     => $row['start_date'] ?: null,
            'end_date'       => $row['end_date'] ?: null,

            'updated_at'     => now(),
        ];

        DB::table('draft_order_items')->updateOrInsert(
            [
                'draft_order_id' => $payload['draft_order_id'],
                'variant_id'     => $payload['variant_id'],
                'vendor_id'      => $payload['vendor_id'],
            ],
            array_merge($payload, ['created_at' => now()])
        );

        $this->dispatch('toast', type: 'success', msg: 'Saved.');
    }

    public function saveAll(): void
    {
        foreach (array_keys($this->rows) as $vid) {
            $this->saveRow((int) $vid);
        }
    }

    public function render(): View
    {
        if (trim($this->subtype) === '') {
            $empty = collect()->paginate($this->perPage, ['*'], $this->paginatorName);
            return view('livewire.signup.zone-variants-list', ['page' => $empty]);
        }

        $page = $this->baseQuery()->paginate($this->perPage, ['*'], $this->paginatorName);
        $this->syncPageRows($page->items());

        return view('livewire.signup.zone-variants-list', ['page' => $page]);
    }
}
