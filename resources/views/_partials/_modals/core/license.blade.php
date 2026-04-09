@php use Illuminate\Support\Facades\Session; @endphp
<div class="modal fade" id="licenseStatusModal" tabindex="-1" role="dialog" aria-labelledby="licenseStatusModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3"><i class="bx bx-shield-check fs-3"></i></div>
          <h5 class="modal-title modal-title-hitech" id="licenseStatusModalLabel">License Status</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close"><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body modal-body-hitech text-center">
        @if(Session::get('licenseStatus', false))
          <div class="mb-3"><i class="bx bx-check-circle text-success" style="font-size:3rem;"></i></div>
          <p class="fw-semibold">You're running a genuine copy of <strong>{{ config('variables.templateName') }}</strong>.</p>
        @else
          <div class="mb-3"><i class="bx bx-error-circle text-danger" style="font-size:3rem;"></i></div>
          <p class="fw-semibold text-danger mb-4">You are running an unlicensed copy of <strong>{{ config('variables.templateName') }}</strong>.</p>
          <div class="text-start mb-4">
            <label class="form-label-hitech" for="licenseKey">License Key</label>
            <input class="form-control form-control-hitech" id="licenseKey" name="licenseKey" placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" />
          </div>
          <button class="btn btn-hitech w-100 mb-3">Activate License</button>
          <div class="text-muted small mb-3">— or —</div>
          <a href="javascript:" class="btn btn-hitech-secondary w-100">
            Activate with <img class="ms-2" src="{{ asset('assets/img/envato.svg') }}" alt="Envato" width="80">
          </a>
        @endif
      </div>
    </div>
  </div>
</div>
