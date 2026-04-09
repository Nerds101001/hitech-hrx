@php
  use Illuminate\Support\Facades\Session;
  $containerFooter = (isset($configData['contentLayout']) && $configData['contentLayout'] === 'compact') ? 'container-xxl' : 'container-fluid';
@endphp

  <!-- Footer-->
<footer class="content-footer footer bg-footer-theme">
  <div class="{{ $containerFooter }}">
    <div class="footer-container d-flex align-items-center justify-content-end py-3">
      {{-- Footer is intentionally empty --}}
    </div>
  </div>
</footer>
<!--/ Footer-->
