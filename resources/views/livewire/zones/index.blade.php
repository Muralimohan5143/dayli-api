<div class="container-fluid py-4">
    @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Zones</h5>
        <div class="d-flex gap-2">
            <input class="form-control" style="width:260px" placeholder="Search..." wire:model.debounce.400ms="search">
            <button class="btn btn-primary" wire:click="openCreate">+ New Zone</button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-items-center mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Pincodes</th>
                        <th>Nagars</th>
                        <th>Focal</th>
                        <th>Lat/Lon</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($zones as $z)
                    <tr>
                        <td>{{ $z->name }}</td>
                        <td><span class="badge bg-light text-dark">{{ $z->code }}</span></td>
                        <td class="text-xs">{{ implode(', ', $z->pincodes->pluck('pin_code')->toArray()) }}</td>
                        <td class="text-xs">{{ $z->nagars }}</td>
                        <td class="text-xs">{{ $z->focal_pt }}</td>
                        <td class="text-xs">{{ $z->focal_lat }}, {{ $z->focal_lon }}</td>
                        <td>
                            <span class="badge {{ ($z->status ?? 'active')==='active' ? 'bg-success' : 'bg-secondary' }}">
                                {{ ucfirst($z->status ?? 'active') }}
                            </span>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary" wire:click="openEdit({{ $z->id }})">Edit</button>
                            <button class="btn btn-sm btn-outline-danger" wire:click="delete({{ $z->id }})" onclick="return confirm('Delete this zone?')">Delete</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted">No zones found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $zones->links() }}</div>
    </div>

    {{-- Modal --}}
    <div wire:ignore.self class="modal fade" id="zoneModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">{{ $mode==='create' ? 'New Zone' : 'Edit Zone' }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input class="form-control" wire:model.defer="name">
                            @error('name') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Code</label>
                            <input class="form-control" wire:model.defer="code" placeholder="zone_kuk">
                            @error('code') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Pincodes</label>
                            @foreach($pincodes as $i => $pin)
                            <div class="d-flex mb-2">
                                <input class="form-control me-2" wire:model.defer="pincodes.{{ $i }}" placeholder="Enter pincode">
                                <button type="button" class="btn btn-outline-danger btn-sm" wire:click="removePincodeField({{ $i }})">X</button>
                            </div>
                            @endforeach
                            <button type="button" class="btn btn-outline-success btn-sm" wire:click="addPincodeField">+ Add Pincode</button>                            
                            @error('pincodes') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror

                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Nagars (CSV)</label>
                            <input class="form-control"
                                wire:model.defer="nagars"
                                wire:blur="normalizeNagars"
                                placeholder="Sindhu Estate,LIG Colony,Gandhi Nagar">
                            <small class="text-muted">Separate nagars with commas</small>
                            @error('nagars') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Focal Point</label>
                            <input class="form-control" wire:model.defer="focal_pt" placeholder="KPHB Metro">
                            @error('focal_pt') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Lat</label>
                            <input class="form-control" wire:model.defer="focal_lat">
                            @error('focal_lat') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Lon</label>
                            <input class="form-control" wire:model.defer="focal_lon">
                            @error('focal_lon') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" wire:model.defer="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            @error('status') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" wire:click="save">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script>
 // document.addEventListener('livewire:load', () => {
    // Livewire v3 dispatch → window event
    window.addEventListener('zone-modal-open', () => {
      const el = document.getElementById('zoneModal');
      const modal = bootstrap.Modal.getOrCreateInstance(el);
      modal.show();
    });

    window.addEventListener('zone-modal-close', () => {
      const el = document.getElementById('zoneModal');
      const modal = bootstrap.Modal.getOrCreateInstance(el);
      modal.hide();
    });
  //});
</script>


</div>