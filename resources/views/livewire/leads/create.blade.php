<x-layouts.app>
<div class="container mt-4 pb-5">
  <div class="card shadow-sm">
    <div class="card-body">
      <h4 class="text-center mb-4">Create Lead</h4>
      <form id="leadForm" class="needs-validation" action="{{ route('leads.store') }}" method="POST" novalidate>
        @csrf

        <!-- Step 1: Contact Info -->
        <div class="step active" id="step1">
          <div class="section-title">Contact Info</div>
          <div class="mb-3 input-with-icon">
            <i class="fa fa-user input-icon"></i>
            <input type="text" name="first_name" class="form-control" placeholder="First Name" required>
          </div>
          <div class="mb-3 input-with-icon">
            <i class="fa fa-user input-icon"></i>
            <input type="text" name="last_name" class="form-control" placeholder="Last Name">
          </div>
          <div class="mb-3 input-with-icon">
            <i class="fa fa-phone input-icon"></i>
            <input type="tel" name="phone" class="form-control" placeholder="Phone" required>
          </div>
          <div class="mb-3 input-with-icon">
            <i class="fa fa-phone input-icon"></i>
            <input type="tel" name="alternate_phone" class="form-control" placeholder="Alternate Phone">
          </div>
          <div class="mb-3 input-with-icon">
            <i class="fa fa-envelope input-icon"></i>
            <input type="email" name="email" class="form-control" placeholder="Email">
          </div>
          <div class="d-grid">
            <button type="button" class="btn btn-primary" onclick="nextStep(2)">Next</button>
          </div>
        </div>

        <!-- Step 2: Address Info -->
        <div class="step" id="step2">
          <div class="section-title">Address</div>
          <div class="mb-3 input-with-icon">
            <i class="fa fa-map-marker-alt input-icon"></i>
            <input type="text" name="address1" class="form-control" placeholder="Address Line 1">
          </div>
          <div class="mb-3 input-with-icon">
            <i class="fa fa-map-marker-alt input-icon"></i>
            <input type="text" name="address2" class="form-control" placeholder="Address Line 2">
          </div>
          <div class="mb-3 row">
            <div class="col-6 input-with-icon">
              <i class="fa fa-compass input-icon"></i>
              <input type="text" name="collected_lat" id="lat" class="form-control" placeholder="Latitude">
            </div>
            <div class="col-6 input-with-icon">
              <i class="fa fa-compass input-icon"></i>
              <input type="text" name="collected_lng" id="lon" class="form-control" placeholder="Longitude">
            </div>
          </div>
          <div class="mb-3 input-with-icon">
            <i class="fa fa-city input-icon"></i>
            <input type="text" name="city" id="city" class="form-control" placeholder="City">
          </div>
          <div class="mb-3 input-with-icon">
            <i class="fa fa-map-pin input-icon"></i>
            <input type="text" name="pincode" id="pin" class="form-control" placeholder="Pin Code">
          </div>
          <div class="mb-3 input-with-icon">
            <i class="fa fa-language input-icon"></i>
            <input type="text" name="lang_locale" class="form-control" placeholder="Preferred Language">
          </div>
          <div class="mb-3 input-with-icon">
            <i class="fa fa-flag input-icon"></i>
            <input type="text" name="state" class="form-control" placeholder="State">
          </div>
          <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-secondary" onclick="nextStep(1)">Back</button>
            <button type="button" class="btn btn-primary" onclick="nextStep(3)">Next</button>
          </div>
        </div>

        <!-- Step 3: Other Info -->
        <div class="step" id="step3">
          <div class="section-title">Additional Info</div>
          <div class="mb-3 input-with-icon">
            <i class="fa fa-tags input-icon"></i>
            <input type="text" name="lead_type" class="form-control" placeholder="Lead Type">
          </div>
          <div class="mb-3 input-with-icon">
            <i class="fa fa-globe input-icon"></i>
            <input type="text" name="zone" class="form-control" placeholder="Zone">
          </div>
          <div class="mb-3 input-with-icon">
            <i class="fa fa-bullhorn input-icon"></i>
            <input type="text" name="source" class="form-control" placeholder="Source">
          </div>
          <div class="mb-3 input-with-icon">
            <i class="fa fa-user-check input-icon"></i>
            <input type="text" name="collected_by" class="form-control" placeholder="Collected By">
          </div>
          <div class="mb-3 input-with-icon">
            <i class="fa fa-info-circle input-icon"></i>
            <textarea name="notes" class="form-control" placeholder="Agent notes..." rows="3"></textarea>
          </div>
          <div class="mb-3 input-with-icon">
            <i class="fa fa-clipboard-check input-icon"></i>
            <input type="text" name="status" class="form-control" placeholder="Status">
          </div>
          <div class="mb-3 input-with-icon">
            <i class="fa fa-calendar-alt input-icon"></i>
            <input type="date" name="follow_up_date" class="form-control" placeholder="Follow-up Date">
          </div>
          <div class="d-flex justify-content-between">
            <button type="button" class="btn btn-secondary" onclick="nextStep(2)">Back</button>
            <button type="submit" class="btn btn-success">Submit Lead</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function nextStep(step) {
  document.querySelectorAll('.step').forEach(div => div.classList.remove('active'));
  document.getElementById('step' + step).classList.add('active');
}

navigator.geolocation.getCurrentPosition(function(position) {
  const lat = position.coords.latitude;
  const lon = position.coords.longitude;
  document.getElementById('lat').value = lat;
  document.getElementById('lon').value = lon;

  fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lon}`)
    .then(res => res.json())
    .then(data => {
      if (data.address) {
        document.getElementById('city').value = data.address.city || data.address.town || '';
        document.getElementById('pin').value = data.address.postcode || '';
      }
    });
});
</script>
</x-layouts.app>
