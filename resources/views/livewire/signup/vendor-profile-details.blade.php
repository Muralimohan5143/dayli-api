{{-- HERO + CARD WRAPPER (same as Contract page) --}}


{{-- PROFILE DETAILS (Address → City & Pincode → Lat/Lng) --}}
<form id="profileForm" wire:submit.prevent="submit">
  <div x-data class="row g-3">
    {{-- Names --}}
    <div class="col-md-6">
      <label class="form-label">First Name</label>
      <input type="text" class="form-control @error('first_name') is-invalid @enderror"
        wire:model.defer="first_name">
      @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
      <label class="form-label">Last Name</label>
      <input type="text" class="form-control @error('last_name') is-invalid @enderror"
        wire:model.defer="last_name">
      @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    {{-- Contact --}}
    <div class="col-md-6">
      <label class="form-label">Phone</label>
      <input type="text" class="form-control @error('phone') is-invalid @enderror"
        placeholder="10 digits" wire:model.defer="phone">
      @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
      <label class="form-label">Email</label>
      <input type="email" class="form-control @error('email') is-invalid @enderror"
        wire:model.defer="email">
      @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    {{-- Address --}}
    <div class="col-12">
      <label class="form-label">Address</label>
      <textarea rows="2" class="form-control @error('address_line1') is-invalid @enderror"
        placeholder="House / Flat, Street, Landmark…"
        wire:model.defer="address_line1"></textarea>
      @error('address_line1') <div class="invalid-feedback">{{ $message }}</div> @enderror

      <details class="mt-2">
        <summary class="small text-muted cursor-pointer">Add address line 2 (optional)</summary>
        <input type="text" class="form-control mt-2 @error('address_line2') is-invalid @enderror"
          placeholder="Area / Locality / Building" wire:model.defer="address_line2">
        @error('address_line2') <div class="invalid-feedback">{{ $message }}</div> @enderror
      </details>

      <div class="mt-2 d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-sm btn-outline-primary"
          wire:click="fillLatLngFromAddress" wire:loading.attr="disabled">
          Auto-fill Lat/Lng from Address
        </button>

        <button type="button" class="btn btn-sm btn-outline-secondary"
          @click="
                        if (navigator.geolocation) {
                          navigator.geolocation.getCurrentPosition(
                            (pos) => $wire.setBrowserLocation(pos.coords.latitude, pos.coords.longitude),
                            () => window.dispatchEvent(new CustomEvent('notify',{detail:'Location permission denied'}))
                          );
                        } else {
                          window.dispatchEvent(new CustomEvent('notify',{detail:'Geolocation not supported'}));
                        }
                      ">
          Use my location
        </button>
      </div>
    </div>

    {{-- City + Pincode --}}
    <div class="col-md-6">
      <label class="form-label">City</label>
      <input type="text" class="form-control @error('city') is-invalid @enderror"
        placeholder="City / Town" wire:model.defer="city">
      @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
      <label class="form-label">Pincode</label>
      <input type="text" maxlength="6" class="form-control @error('pincode') is-invalid @enderror"
        placeholder="6-digit" wire:model.lazy="pincode">
      @error('pincode') <div class="invalid-feedback">{{ $message }}</div> @enderror
      <div class="form-text">Enter 6 digits to auto-detect City.</div>
    </div>

    {{-- Lat / Lng --}}
    <div class="col-md-6">
      <label class="form-label">Latitude</label>
      <input type="text" class="form-control @error('lat') is-invalid @enderror"
        placeholder="e.g., 17.3850" wire:model.defer="lat">
      @error('lat') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
      <label class="form-label">Longitude</label>
      <input type="text" class="form-control @error('lng') is-invalid @enderror"
        placeholder="e.g., 78.4867" wire:model.defer="lng">
      @error('lng') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    {{-- Optional: Nagar / Zone --}}
    <div class="col-md-6">
      <label class="form-label">Nagar / Locality (optional)</label>
      <input type="text" class="form-control @error('nagar') is-invalid @enderror"
        wire:model.defer="nagar">
      @error('nagar') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
      <label class="form-label">Zone (optional)</label>
      <input type="text" class="form-control @error('zone') is-invalid @enderror"
        placeholder="Auto-mapped if left blank" wire:model.defer="zone">
      @error('zone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    {{-- No internal submit button: footer will submit this form --}}
  </div>
</form>