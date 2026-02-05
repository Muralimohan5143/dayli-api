<div>
    <form wire:submit.prevent="submit">
            
    <div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-body">
      <h5 class="card-title text-center mb-4">Create Lead</h5>

      <div class="mb-3">
        <label class="form-label">First Name</label>
        <input type="text" class="form-control" placeholder="Enter first name">
      </div>

      <div class="mb-3">
        <label class="form-label">Last Name</label>
        <input type="text" class="form-control" placeholder="Enter last name">
      </div>

      <div class="mb-3">
        <label class="form-label">Phone</label>
        <input type="tel" class="form-control" placeholder="Enter phone number">
      </div>

      <div class="mb-3">
        <label class="form-label">Address Line 1</label>
        <input type="text" class="form-control" placeholder="Address Line 1">
      </div>

      <div class="mb-3">
        <label class="form-label">Address Line 2</label>
        <input type="text" class="form-control" placeholder="Address Line 2">
      </div>

      <div class="mb-3">
        <label class="form-label">Latitude</label>
        <input type="text" class="form-control" placeholder="Latitude">
      </div>

      <div class="mb-3">
        <label class="form-label">Longitude</label>
        <input type="text" class="form-control" placeholder="Longitude">
      </div>

      <div class="mb-3">
        <label class="form-label">City</label>
        <input type="text" class="form-control" placeholder="City">
      </div>

      <div class="mb-3">
        <label class="form-label">Pin Code</label>
        <input type="text" class="form-control" placeholder="Pin Code">
      </div>

      <div class="d-grid mt-4">
        <button class="btn btn-primary">Submit</button>
      </div>
    </div>
  </div>
</div>

</form>

    <script>
        document.addEventListener('livewire:load', function () {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function (position) {
                    @this.set('collected_lat', position.coords.latitude);
                    @this.set('collected_lng', position.coords.longitude);
                });
            }
        });
    </script>
</div>
