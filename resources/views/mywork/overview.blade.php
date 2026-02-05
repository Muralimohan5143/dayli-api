{{-- resources/views/mywork/overview.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
  <div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
      <h5 class="mb-0">Ops Overview</h5>
      <a href="{{ route('sub-change-requests.index') }}" class="btn btn-sm btn-primary">Supply Contracts</a>
    </div>
    <div class="card-body">
      <p class="text-muted mb-3">Quick links</p>
      <div class="row g-3">
        <div class="col-sm-6 col-lg-3">
          <a class="card h-100 text-decoration-none" href="{{ route('sub-change-requests.index') }}">
            <div class="card-body">
              <div class="h6 mb-1">My Supply Contracts</div>
              <small class="text-muted">Create / review contracts</small>
            </div>
          </a>
        </div>
        <div class="col-sm-6 col-lg-3">
          <a class="card h-100 text-decoration-none" href="{{ route('sub-delivery-actuals.index') }}">
            <div class="card-body">
              <div class="h6 mb-1">My Delivery Actuals</div>
              <small class="text-muted">Capture daily quantities</small>
            </div>
          </a>
        </div>
        <div class="col-sm-6 col-lg-3">
          <a class="card h-100 text-decoration-none" href="{{ route('zones.index') }}">
            <div class="card-body">
              <div class="h6 mb-1">Zones</div>
              <small class="text-muted">Manage zones & coverage</small>
            </div>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
