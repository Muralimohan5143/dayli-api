<?php

namespace App\Http\Livewire\SubChangeRequests;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\SubChangeRequest;
use Carbon\Carbon;

class Edit extends Component
{
    public bool $open = false;
    public ?int $crId = null;

    public ?float $qty = null;
    public ?string $unit = 'pcs';
    public string $frequency_type = 'daily';
    public ?string $start_date = null;
    public ?string $end_date = null;

    protected $listeners = ['scr-open-edit' => 'open'];

    public function render()
    {
        return view('livewire.sub-change-requests.edit');
    }

    public function open(int $id, ?int $productId = null): void
    {
        $this->resetValidation();
        $this->crId = $id;

        $cr = SubChangeRequest::with(['draftOrder.items' => fn($q) => $q->orderByDesc('id')])
            ->findOrFail($id);

        $item = optional($cr->draftOrder?->items?->first());
        $this->qty  = $item?->qty ?? 1;
        $this->unit = $item?->unit ?? 'pcs';

        $this->frequency_type = $cr->frequency_type ?? 'daily';
        $this->start_date     = $cr->start_date ?? Carbon::now()->toDateString();
        $this->end_date       = $cr->end_date;

        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    protected function rules(): array
    {
        return [
            'qty'            => ['required', 'numeric', 'min:0.01'],
            'unit'           => ['required', 'string', 'max:16'],
            'frequency_type' => ['required', 'string', 'max:32'],
            'start_date'     => ['required', 'date'],
            'end_date'       => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function save(): void
    {
        $this->validate();
        if (!$this->crId) return;

        DB::transaction(function () {
            SubChangeRequest::whereKey($this->crId)->update([
                'frequency_type' => $this->frequency_type,
                'start_date'     => $this->start_date,
                'end_date'       => $this->end_date,
            ]);

            $draftId = DB::table('draft_orders')
                ->where('change_request_id', $this->crId)
                ->value('id');

            if ($draftId) {
                DB::table('draft_order_items')
                    ->where('draft_order_id', $draftId)
                    ->orderByDesc('id')
                    ->limit(1)
                    ->update([
                        'qty'        => $this->qty,
                        'unit'       => $this->unit,
                        'updated_at' => now(),
                    ]);
            }
        });

        $this->dispatch('refreshGroupView');
        $this->open = false;
        $this->dispatchBrowserEvent('toast', ['type' => 'success', 'msg' => 'Updated.']);
    }
}
