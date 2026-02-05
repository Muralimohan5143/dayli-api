<form id="profileForm" action="javascript:void(0)" method="post">
    @csrf
    <div class="d-flex justify-content-center">
        <div class="card">
            <div class="card-body">
                <div class="row g-3">
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

                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control @error('phone') is-invalid @enderror"
                            wire:model.defer="phone">
                        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror"
                            wire:model.defer="email">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Address Line 1</label>
                        <input type="text" class="form-control @error('address_line1') is-invalid @enderror"
                            wire:model.defer="address_line1" placeholder="Street / Area">
                        @error('address_line1') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Address Line 2</label>
                        <input type="text" class="form-control @error('address_line2') is-invalid @enderror"
                            wire:model.defer="address_line2" placeholder="Street / Area">
                        @error('address_line2') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- City + Pincode --}}
                    <div class="col-md-6">
                        <label class="form-label">City</label>
                        <input type="text" class="form-control @error('city') is-invalid @enderror"
                            wire:model.defer="city" placeholder="e.g. Bangalore">
                        @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Pincode</label>
                        <input type="text"
                            class="form-control @error('pincode') is-invalid @enderror"
                            wire:model.defer="pincode"
                            id="pincodeInput"
                            maxlength="6"
                            placeholder="e.g. 560001">
                        @error('pincode') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex justify-content-end mb-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                            onclick="dayliUseMyLocation()">
                            Use my location
                        </button>
                    </div>

                    <!-- Latitude -->
                    <div class="col-md-6">
                        <label class="form-label">Latitude</label>
                        <input type="text" id="latInput"
                            class="form-control @error('lat') is-invalid @enderror"
                            wire:model.defer="lat" readonly>
                        @error('lat') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    <!-- Longitude -->
                    <div class="col-md-6">
                        <label class="form-label">Longitude</label>
                        <input type="text" id="lngInput"
                            class="form-control @error('lng') is-invalid @enderror"
                            wire:model.defer="lng" readonly>
                        @error('lng') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    <!-- Nagar (sublocality) -->
                    <div class="col-md-6">
                        <label class="form-label">Nagar / Locality</label>
                        <input type="text" id="nagarInput"
                            class="form-control"
                            wire:model.defer="nagar" readonly>
                    </div>

                    <!-- Zone (derived) -->
                    <div class="col-md-6">
                        <label class="form-label">Zone</label>
                        <input type="text" id="zoneInput"
                            class="form-control"
                            wire:model.defer="zone" readonly>
                    </div>
                </div>

                <small class="text-muted d-block mt-3">
                    We use pincode/coordinates to map you to the right zone.
                </small>
            </div>
        </div>
    </div>
</form>
{{-- Scripts --}}
<script>
    function dayliUseMyLocation() {
        if (!navigator.geolocation) {
            alert('Geolocation is not supported by your browser.');
            return;
        }
        navigator.geolocation.getCurrentPosition(onGeoOk, onGeoErr, {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        });
    }

    async function onGeoOk(pos) {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;

        // Fill lat/lng fields
        document.getElementById('latInput').value = lat;
        document.getElementById('lngInput').value = lng;
        $wire.set('lat', lat);
        $wire.set('lng', lng);

        // Reverse geocode to get pincode + nagar
        await dayliReverseGeocode(lat, lng);
    }

    function onGeoErr(err) {
        console.warn('Geolocation error:', err);
        alert('Could not get your location. Please allow location permissions or enter details manually.');
    }

    async function dayliReverseGeocode(lat, lng) {
        try {
            const endpoint = `https://maps.googleapis.com/maps/api/geocode/json?latlng=${lat},${lng}&key={{ config('services.google.maps_api_key') }}`;
            const res = await fetch(endpoint);
            const data = await res.json();

            if (data.status === 'OK' && data.results.length) {
                const best = data.results[0];
                let pincode = '';
                let nagar = '';

                const ac = best.address_components || [];
                for (const c of ac) {
                    if (c.types.includes('postal_code')) pincode = c.long_name;
                    if (c.types.includes('sublocality') || c.types.includes('neighborhood')) {
                        if (!nagar) nagar = c.long_name;
                    }
                }

                if (pincode) {
                    document.getElementById('pincodeInput').value = pincode;
                    $wire.set('pincode', pincode);
                }
                if (nagar) {
                    document.getElementById('nagarInput').value = nagar;
                    $wire.set('nagar', nagar);
                }

                const zone = dayliComputeZoneClient(pincode);
                if (zone) {
                    document.getElementById('zoneInput').value = zone;
                    $wire.set('zone', zone);
                } else {
                    $wire.call('mapZoneFromGeo', lat, lng, pincode).then(z => {
                        if (z) {
                            document.getElementById('zoneInput').value = z;
                            $wire.set('zone', z);
                        }
                    });
                }
            } else {
                console.warn('Geocode failed:', data.status, data.error_message);
            }
        } catch (e) {
            console.error('Reverse geocode error:', e);
        }
    }

    async function fetchLatLng(pincode) {
        if (!pincode || pincode.length < 6) return;
        const res = await fetch(
            `https://maps.googleapis.com/maps/api/geocode/json?address=${pincode},India&key={{ config('services.google.maps_api_key') }}`
        );
        const data = await res.json();

        if (data.status === 'OK' && data.results.length > 0) {
            const loc = data.results[0].geometry.location;
            document.getElementById('latInput').value = loc.lat;
            document.getElementById('lngInput').value = loc.lng;
            $wire.set('lat', loc.lat);
            $wire.set('lng', loc.lng);

            await dayliReverseGeocode(loc.lat, loc.lng);
        }
    }

    function dayliComputeZoneClient(pincode) {
        if (!pincode) return '';
        // Example client mapping:
        // if (pincode.startsWith('5600')) return 'Bengaluru Central';
        // if (pincode.startsWith('4000')) return 'Mumbai Core';
        return '';
    }
</script>