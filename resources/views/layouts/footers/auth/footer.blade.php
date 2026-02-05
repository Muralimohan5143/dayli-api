@php use Illuminate\Support\Facades\Route; @endphp
<footer class="footer py-3">
  <div class="container-fluid">
    <div class="row align-items-center justify-content-between">
      <div class="col-md-6 text-xs text-secondary">
        © {{ now()->year }} <a href="{{ url('/') }}" class="font-weight-bold text-reset">LeelaShop</a>.
      </div>
      <div class="col-md-6">
        <ul class="nav justify-content-end">
          <li class="nav-item"><a class="nav-link text-secondary" href="{{ Route::has('settings') ? route('settings') : '#' }}">Settings</a></li>
          <li class="nav-item"><a class="nav-link text-secondary" href="{{ Route::has('help') ? route('help') : '#' }}">Help</a></li>
          <li class="nav-item"><a class="nav-link text-secondary" href="{{ Route::has('privacy') ? route('privacy') : '#' }}">Privacy</a></li>
        </ul>
      </div>
    </div>
  </div>
</footer>
