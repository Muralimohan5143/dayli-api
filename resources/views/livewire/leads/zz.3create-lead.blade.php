<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Lead</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    .input-icon {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: #888;
    }
    .input-with-icon {
      position: relative;
    }
    .input-with-icon input,
    .input-with-icon textarea {
      padding-left: 2.5rem;
    }
    .sticky-submit {
      position: fixed;
      bottom: 0;
      left: 0;
      width: 100%;
      z-index: 1030;
    }
    .section-title {
      margin-top: 1.5rem;
      font-weight: 600;
      font-size: 1.1rem;
    }
    .step {
      display: none;
    }
    .step.active {
      display: block;
    }
  </style>
</head>
<body class="bg-light">
  <div class="container mt-4 pb-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h4 class="text-center mb-4">Create Lead</h4>
        <form id="leadForm" class="needs-validation" novalidate>

          <!-- Step 1: Contact Info -->
          <div class="step active" id="step1">
            <div class="section-title">Contact Info</div>
            <div class="mb-3 input-with-icon">
              <i class="fa fa-user input-icon"></i>
              <input type="text" class="form-control" placeholder="First Name" required>
            </div>

            <div class="mb-3 input-with-icon">
              <i class="fa fa-user input-icon"></i>
              <input type="text" class="form-control" placeholder="Last Name" required>
            </div>

            <div class="mb-3 input-with-icon">
              <i class="fa fa-phone input-icon"></i>
              <input type="tel" class="form-control" placeholder="Phone" required>
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
              <input type="text" class="form-control" placeholder="Address Line 1" required>
            </div>

            <div class="mb-3 input-with-icon">
              <i class="fa fa-map-marker-alt input-icon"></i>
              <input type="text" class="form-control" placeholder="Address Line 2">
            </div>

            <div class="mb-3 row">
              <div class="col-6 input-with-icon">
                <i class="fa fa-compass input-icon"></i>
                <input type="text" class="form-control" placeholder="Latitude" id="lat">
              </div>
              <div class="col-6 input-with-icon">
                <i class="fa fa-compass input-icon"></i>
                <input type="text" class="form-control" placeholder="Longitude" id="lon">
              </div>
            </div>

            <div class="mb-3 input-with-icon">
              <i class="fa fa-city input-icon"></i>
              <input type="text" class="form-control" placeholder="City" id="city">
            </div>

            <div class="mb-3 input-with-icon">
              <i class="fa fa-envelope input-icon"></i>
              <input type="text" class="form-control" placeholder="Pin Code" id="pin">
            </div>

            <div class="d-flex justify-content-between">
              <button type="button" class="btn btn-secondary" onclick="nextStep(1)">Back</button>
              <button type="button" class="btn btn-primary" onclick="nextStep(3)">Next</button>
            </div>
          </div>

          <!-- Step 3: Notes & Submit -->
          <div class="step" id="step3">
            <div class="section-title">Notes (Optional)</div>
            <div class="mb-3 input-with-icon">
              <i class="fa fa-comment input-icon"></i>
              <textarea class="form-control" placeholder="Agent notes..." rows="3"></textarea>
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

    (() => {
      'use strict';
      const form = document.getElementById('leadForm');
      form.addEventListener('submit', event => {
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
        }
        form.classList.add('was-validated');
      });
    })();

    // Auto-detect geolocation and fetch city/pin
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
