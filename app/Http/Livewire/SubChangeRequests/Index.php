<?php

namespace App\Http\Livewire\SubChangeRequests;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\SubChangeRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;

    protected $queryString = ['search'];

    public function updatingSearch()
    {
        $this->resetPage();
    }
    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function render()
    {
        $s = trim($this->search);

        $requests = SubChangeRequest::query()
            ->visibleTo(Auth::id())
            ->addSelect([
                // 🧩 First product title from linked draft_order_items
                'first_product_title' => DB::table('draft_order_items as doi')
                    ->join('draft_orders as do', 'do.id', '=', 'doi.draft_order_id')
                    ->join('products as p', 'p.product_id', '=', 'doi.product_id')
                    ->whereColumn('do.change_request_id', 'sub_change_requests.id')
                    ->orderBy('doi.id')
                    ->limit(1)
                    ->select('p.title'),

                // 🧮 Sum of all quantities from linked draft_order_items
                'items_qty_sum' => DB::table('draft_order_items as doi')
                    ->join('draft_orders as do', 'do.id', '=', 'doi.draft_order_id')
                    ->whereColumn('do.change_request_id', 'sub_change_requests.id')
                    ->selectRaw('COALESCE(SUM(doi.qty),0)'),

                // 🔢 Count of items (optional, useful for debugging)
                'items_count' => DB::table('draft_order_items as doi')
                    ->join('draft_orders as do', 'do.id', '=', 'doi.draft_order_id')
                    ->whereColumn('do.change_request_id', 'sub_change_requests.id')
                    ->selectRaw('COUNT(*)'),
            ])
            ->with(['customer:id,name'])
            ->when($s !== '', function ($q) use ($s) {
                $q->where(function ($w) use ($s) {
                    $w->whereHas('customer', fn($cq) => $cq->where('name', 'like', "%{$s}%"))
                        ->orWhere('status', 'like', "%{$s}%");
                });
            })
            // ⚙️ Optional: show only requests that have items
            // ->whereExists(function ($q) {
            //     $q->from('draft_orders as do')
            //       ->join('draft_order_items as doi', 'doi.draft_order_id', '=', 'do.id')
            //       ->whereColumn('do.change_request_id', 'sub_change_requests.id');
            // })
            ->orderByDesc('id') // show newest first (those with items)
            ->paginate($this->perPage);

        return view('livewire.sub-change-requests.index', compact('requests'));
    }
}
