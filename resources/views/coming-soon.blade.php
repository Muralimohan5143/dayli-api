<x-layouts.app>
  <div class="container-fluid py-4">
    <div class="row justify-content-center">
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
          <div class="card-header p-4 bg-gradient-primary border-0 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
              <div class="icon icon-shape bg-white text-primary shadow text-center border-radius-md me-3">
                <i class="ni ni-settings-gear-65"></i>
              </div>
              <div>
                <h5 class="text-white mb-0">Coming Soon</h5>
                <p class="text-sm text-white-50 mb-0">We’re building this module as we speak.</p>
              </div>
            </div>
            <span class="badge bg-white text-primary">WIP</span>
          </div>

          <div class="card-body p-4">
            <h6 class="mb-2">What to expect</h6>
            <ul class="list-unstyled mb-4">
              <li class="d-flex align-items-start mb-2">
                <i class="ni ni-check-bold text-success me-2"></i>
                <span>Polished UI with Soft‑UI components</span>
              </li>
              <li class="d-flex align-items-start mb-2">
                <i class="ni ni-check-bold text-success me-2"></i>
                <span>Role-based access using Spatie Permissions</span>
              </li>
              <li class="d-flex align-items-start mb-2">
                <i class="ni ni-check-bold text-success me-2"></i>
                <span>Zone-aware data and smart filters</span>
              </li>
            </ul>

            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <small class="text-muted">Progress</small>
                <small class="text-muted">In development</small>
              </div>
              <div class="progress" style="height: 6px;">
                <div class="progress-bar bg-gradient-primary" role="progressbar" style="width: 35%;" aria-valuenow="35" aria-valuemin="0" aria-valuemax="100"></div>
              </div>
            </div>

            <div class="d-flex gap-2">
              <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">
                <i class="ni ni-bold-left me-1"></i> Back
              </a>
              <a href="{{ route('overview') }}" class="btn btn-primary">
                <i class="ni ni-tv-2 me-1"></i> Go to Dashboard
              </a>
            </div>
          </div>

          <div class="card-footer p-3 text-center">
            <small class="text-muted">
              If you need this sooner, ping the team — we can bump the priority.
            </small>
          </div>
        </div>
      </div>
    </div>
  </div>
</x-layouts.app>
