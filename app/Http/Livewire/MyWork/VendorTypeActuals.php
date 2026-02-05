<?php


namespace App\Http\Livewire\MyWork;


use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\SubscriptionType;
use App\Models\DraftOrderItem;
use App\Models\DraftOrder; // assumed parent
use App\Models\ProductVariant; // optional if you need names
use App\Models\SubDeliveryActual; // model included below


class VendorTypeActuals extends Component
{
    use WithPagination;


    public int $typeId;
    public string $deliverDate; // yyyy-mm-dd


    /** @var array<int, float|null> */
    public array $actuals = []; // keyed by draft_order_item_id


    /** @var array<int, string|null> */
    public array $notes = []; // optional notes per row


    public string $search = '';
    public int $perPage = 50;


    public function mount(int $typeId): void
    {
        $this->typeId = $typeId;
        $this->deliverDate = now()->toDateString();
    }


    public function updatingSearch(): void
    {
        $this->resetPage();
    }


    public function rules(): array
    {
        return [
            'deliverDate' => ['required', 'date'],
            'actuals' => ['array'],
            'actuals.*' => ['nullable', 'numeric', 'gte:0', 'lte:999999'],
            'notes' => ['array'],
            'notes.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function save(): void
    {
        $this->validate();
        $vendorId = Auth::id();
        abort_unless($vendorId, 403);


        DB::transaction(function () use ($vendorId) {
            foreach ($this->actuals as $draftItemId => $qty) {
                if ($qty === null || $qty === '') {
                    continue;
                }


                $draftItem = DraftOrderItem::query()
                    ->whereKey($draftItemId)
                    ->where('vendor_id', $vendorId)
                    ->first();
                if (! $draftItem) {
                    continue;
                }


                SubDeliveryActual::upsert(
                    [
                        'vendor_id' => $vendorId,
                        'draft_order_item_id' => $draftItem->id,
                        'deliver_date' => $this->deliverDate,
                        'qty_actual' => (float)$qty,
                        'unit' => $draftItem->unit,
                        'notes' => $this->notes[$draftItemId] ?? null,
                    ],
                    // unique-by
                    ['vendor_id', 'draft_order_item_id', 'deliver_date'],
                    // update columns
                    ['qty_actual', 'unit', 'notes', 'updated_at']
                );
            }
        });
        $this->dispatchBrowserEvent('toast', ['type' => 'success', 'message' => 'Actuals saved']);
    }


    public function getTypeProperty(): ?SubscriptionType
    {
        return SubscriptionType::find($this->typeId);
    }
    public function getRowsProperty()
    {
        $vendorId = Auth::id();
        return DraftOrderItem::query()
            ->select('draft_order_items.*', 'do.customer_id', 'do.subscription_type_id', 'pv.name as variant_name', 'p.title as product_name')
            ->join('draft_orders as do', 'do.id', '=', 'draft_order_items.draft_order_id')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'draft_order_items.variant_id')
            ->leftJoin('products as p', 'p.id', '=', 'draft_order_items.product_id')
            ->where('draft_order_items.vendor_id', $vendorId)
            ->where('do.subscription_type_id', $this->typeId)
            ->when($this->search, function ($q) {
                $s = "%{$this->search}%";
                $q->where(function ($q) use ($s) {
                    $q->where('p.title', 'like', $s)
                        ->orWhere('pv.name', 'like', $s);
                });
            })
            ->orderBy('p.title')
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('livewire.mywork.vendor-type-actuals', [
            'type' => $this->type,
            'rows' => $this->rows,
        ])->layout('layouts.app');
    }
}
