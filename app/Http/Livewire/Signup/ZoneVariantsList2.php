<?php

namespace App\Http\Livewire\Signup;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Zone-aware variant list for a given category + subtype.
 *
 * Props:
 * - string $category  (context only)
 * - string $subtype   (matches products.product_sub_type)
 * - int    $zoneId
 * - ?int   $vendorId
 */
class ZoneVariantsList extends Component
{
    use WithPagination;

    // Incoming props
    public string $category = '';
    public string $subtype  = '';
    public int    $zoneId   = 1;
    public ?int   $vendorId = null;

    // UI state
    #[Url(as: 'q', keep: true)]
    public string $q = '';

    public bool $onlyActive = false;

    /**
     * Row bag keyed by variant_id.
     * Each row: ['product','title','sku','mrp','mode','percent','amount','cost','active_in_zone']
     */
    public array $rows = [];

    // Pagination config
    protected $paginationTheme = 'bootstrap';
    public int $perPage = 15;

    public function mount(
        string $category = '',
        string $subtype = '',
        int $zoneId = 1,
        ?int $vendorId = null
    ): void {
        $this->category = $category;
        $this->subtype  = $subtype;
        $this->zoneId   = $zoneId ?: 1;
        $this->vendorId = $vendorId;
    }

    /* ------------ UI Actions ------------ */

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function toggleOnlyActive(): void
    {
        $this->onlyActive = !$this->onlyActive;
        $this->resetPage();
    }

    public function setMode(int $variantId, string $mode): void
    {
        if (!in_array($mode, ['percent', 'amount'], true)) return;

        $this->ensureRow($variantId);
        $this->rows[$variantId]['mode'] = $mode;

        // Clear the “other” field
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

    /* ------------ Internals ------------ */

    private function ensureRow(int $variantId): void
    {
        if (!isset($this->rows[$variantId])) {
            $this->rows[$variantId] = [
                'product'        => '',
                'title'          => '',
                'sku'            => '',
                'mrp'            => 0.0,
                'mode'           => 'percent',
                'percent'        => 0.0,
                'amount'         => 0.0,
                'cost'           => 0.0,
                'active_in_zone' => false,
                'selected'       => false,   // 👈 added
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

    /**
     * Build the base query pulling variants for this subtype + zone.
     * NOTE: adds product title as `product_title`.
     */
    private function baseQuery()
    {
        // Defensive: without a subtype label we should return no rows
        $subtype = trim((string) $this->subtype);
        if ($subtype === '') {
            // return an empty query
            return DB::table('variants as v')->whereRaw('1 = 0');
        }

        $query = DB::table('variants as v')
            ->join('products as p', 'p.product_id', '=', 'v.product_id')
            ->leftJoin('zone_product_variants as zpv', function ($j) {
                $j->on('zpv.product_id', '=', 'v.product_id')
                    ->on('zpv.variant_id', '=', 'v.variant_id')
                    ->where('zpv.zone_id', '=', $this->zoneId);
            })
            // Match the *subtype* (case-insensitive)
            ->whereRaw('LOWER(COALESCE(p.product_sub_type, "")) = ?', [mb_strtolower($subtype)]);

        // Optional text search (variant title / SKU / product title)
        if ($this->q !== '') {
            $q = '%' . mb_strtolower($this->q) . '%';
            $query->where(function ($w) use ($q) {
                $w->whereRaw('LOWER(v.title) LIKE ?', [$q])
                    ->orWhereRaw('LOWER(v.sku) LIKE ?', [$q])
                    ->orWhereRaw('LOWER(p.title) LIKE ?', [$q]);
            });
        }

        return $query->select([
            'p.title as product_title',
            'v.variant_id',
            'v.product_id',
            'v.title as variant_title',
            'v.sku',
            'v.price as mrp',
            DB::raw('COALESCE(zpv.is_active, 0) as in_zone'),
        ])
            ->orderBy('p.title')
            ->orderBy('v.position');
    }
    private function syncPageRows(array $pageRows): void
    {
        foreach ($pageRows as $row) {
            $vid = (int) $row->variant_id;

            if (!isset($this->rows[$vid])) {
                $this->rows[$vid] = [
                    'product'        => $row->product_title,
                    'title'          => $row->variant_title,
                    'sku'            => $row->sku,
                    'mrp'            => (float) $row->mrp,
                    'mode'           => 'percent',
                    'percent'        => 0.0,
                    'amount'         => 0.0,
                    'cost'           => (float) $row->mrp,
                    'active_in_zone' => ((int) $row->in_zone) === 1,
                ];
            } else {
                $this->rows[$vid]['product'] = $row->product_title;
                $this->rows[$vid]['title']   = $row->variant_title;
                $this->rows[$vid]['sku']     = $row->sku;
            }

            $this->recalcRow($vid);
        }
    }

    public function render(): View
    {
        if (trim($this->subtype) === '') {
            $empty = collect()->paginate($this->perPage);
            return view('livewire.signup.zone-variants-list', ['page' => $empty]);
        }

        $page = $this->baseQuery()->paginate($this->perPage);
        $this->syncPageRows($page->items());

        return view('livewire.signup.zone-variants-list', ['page' => $page]);
    }
}
