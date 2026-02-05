<?php

namespace App\Http\Livewire\Zones;

use App\Models\Zone;
use App\Models\ZonePincode;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $mode = 'create'; // create|edit
    public ?int $editingId = null;

    // form fields
    public $name = '', $code = '', $nagars = '', $focal_pt = '', $focal_lon = '', $focal_lat = '', $status = 'active';
    public array $pincodes = [];

    protected function rules(): array
    {
        $id = $this->editingId ?? 'NULL';
        return [
            'name'      => ['required', 'string', 'max:255'],
            'code'      => ['required', 'string', 'max:255', "unique:zone,code,{$id}"],
            'focal_pt'  => ['nullable', 'string', 'max:255'],
            'focal_lon' => ['nullable', 'numeric', 'between:-180,180'],
            'focal_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'status'    => ['required', 'in:active,inactive'],
            'pincodes.*' => ['required', 'string', 'max:10'],
            'nagars' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (strlen($value) > 255) {
                    $fail('The nagars list is too long.');
                }
            }],
        ];
    }

    public function openCreate()
    {
        $this->resetForm();
         $this->pincodes = ['518002']; 
        $this->mode = 'create';
        $this->dispatch('zone-modal-open');
    }

    public function openEdit(int $id)
    {
        $zone = Zone::with('pincodes')->findOrFail($id);
        $this->fill($zone->only(['name', 'code', 'nagars', 'focal_pt', 'focal_lon', 'focal_lat', 'status']));
        $this->pincodes = $zone->pincodes->pluck('pin_code')->toArray();
        $this->editingId = $id;
        $this->mode = 'edit';
        $this->dispatch('zone-modal-open');
    }


    // CREATE or UPDATE
public function save()
{
    $this->normalizeNagars();

    // Clean + dedupe pincodes
    $this->pincodes = array_values(array_filter(array_map('trim', $this->pincodes)));
    $this->pincodes = array_values(array_unique($this->pincodes));

    // Validate base fields
    $this->validate([
        'name'      => 'required|string|max:255',
        'code'      => 'required|string|max:255|unique:zone,code,' . ($this->editingId ?? 'NULL'),
        'nagars'    => 'nullable|string|max:255',
        'focal_pt'  => 'nullable|string|max:255',
        'focal_lon' => 'nullable|numeric|between:-180,180',
        'focal_lat' => 'nullable|numeric|between:-90,90',
        'status'    => 'required|in:active,inactive',
        'pincodes.*'=> 'nullable|string|max:10',
    ]);

    // Check duplicates across other zones
    $duplicatePins = \App\Models\ZonePincode::query()
        ->whereIn('pin_code', $this->pincodes)
        ->when($this->editingId, fn($q) => $q->where('zone_id', '!=', $this->editingId))
        ->pluck('pin_code')
        ->toArray();

    if (!empty($duplicatePins)) {
        $this->addError('pincodes', 'These pincodes already belong to another zone: '.implode(', ', $duplicatePins));
        return;
    }

    // Persist zone
    $payload = [
        'name'      => $this->name,
        'code'      => $this->code,
        'nagars'    => $this->nagars,
        'focal_pt'  => $this->focal_pt,
        'focal_lon' => $this->focal_lon,
        'focal_lat' => $this->focal_lat,
        'status'    => $this->status,
    ];

    $zone = $this->editingId
        ? tap(Zone::findOrFail($this->editingId))->update($payload)
        : Zone::create($payload);

    // Replace pincodes
    $zone->pincodes()->delete();
    foreach ($this->pincodes as $pin) {
        if ($pin !== '') {
            $zone->pincodes()->create(['pin_code' => $pin]);
        }
    }

    $this->dispatch('zone-modal-close');
    session()->flash('success', $this->editingId ? 'Zone updated.' : 'Zone created.');
    $this->resetForm();
}


// DELETE
public function delete(int $id)
{
    Zone::findOrFail($id)->delete();
    session()->flash('success', 'Zone deleted.');
}

    // public function save()
    // {
    //     // Always clean up nagars before validating/saving
    //     $this->normalizeNagars();

    //     $this->validate();

    //     if ($this->editingId) {
    //         Zone::findOrFail($this->editingId)->update($this->only([
    //             'name',
    //             'code',
    //             'nagars',
    //             'focal_pt',
    //             'focal_lat',
    //             'focal_lon',
    //             'status'
    //         ]));
    //         $zone = Zone::find($this->editingId);
    //     } else {
    //         $zone = Zone::create($this->only([
    //             'name',
    //             'code',
    //             'nagars',
    //             'focal_pt',
    //             'focal_lat',
    //             'focal_lon',
    //             'status'
    //         ]));
    //     }

    //     // Update pincodes in the related table
    //     $zone->pincodes()->delete();
    //     foreach ($this->pincodes as $pin) {
    //         if ($pin) {
    //             $zone->pincodes()->create(['pin_code' => trim($pin)]);
    //         }
    //     }

    //     $this->emit('zone-modal-close');
    //     session()->flash('success', 'Zone saved successfully.');
    //     $this->resetForm();
    // }


    // public function delete(int $id)
    // {
    //     Zone::findOrFail($id)->delete();
    //     session()->flash('success', 'Zone deleted.');
    // }

    public function addPincodeField()
    {
        $this->pincodes[] = '';
    }

    public function removePincodeField($index)
    {
        unset($this->pincodes[$index]);
        $this->pincodes = array_values($this->pincodes);
    }

    public function resetForm()
    {
        $this->reset(['name', 'code', 'nagars', 'focal_pt', 'focal_lon', 'focal_lat', 'status', 'pincodes', 'editingId']);
        $this->status = 'active';
    }

    public function normalizeNagars()
    {
        if (!empty($this->nagars)) {
            // Split CSV into array, trim spaces, remove empty values
            $list = array_filter(array_map('trim', explode(',', $this->nagars)));

            // Remove duplicates
            $list = array_unique($list);

            // Sort alphabetically (case-insensitive)
            natcasesort($list);

            // Implode back into CSV with a consistent comma+space format
            $this->nagars = implode(', ', $list);
        }
    }

    public function render()
    {
        $zones = Zone::with('pincodes')
            ->when(
                $this->search,
                fn($q) =>
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%")
                    ->orWhereHas('pincodes', fn($p) => $p->where('pin_code', 'like', "%{$this->search}%"))
            )
            ->orderBy('name')->paginate(10);

        return view('livewire.zones.index', compact('zones'))->layout('layouts.app');
    }

    
}
