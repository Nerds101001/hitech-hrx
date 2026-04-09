@php
  if(isset($licenseInfo['activation'])) {
    if(!auth()->check()){
     $pageConfigs = ['myLayout' => 'blank', 'displayCustomizer' => false];
    }
  } else {
    $pageConfigs = ['myLayout' => 'blank', 'displayCustomizer' => false];
  }
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Activation')


@section('page-script')
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const activationForm = document.querySelector('form[action="{{ route('activation.activate') }}"]');
      if (activationForm) {
        activationForm.addEventListener('submit', function (e) {
          // Show SweetAlert loader when form is submitted
          Swal.fire({
            title: 'Activating...',
            text: 'Please wait while we activate your license.',
            allowOutsideClick: false,
            didOpen: () => {
              Swal.showLoading();
            }
          });
        });
      }
    });
  </script>
@endsection

@section('content')
  <div class="container mt-5">
    @if(isset($licenseInfo['activation']))
      <!-- License Details Card -->
      <!-- Activation Details Card -->
      <div class="card mb-4">
        <div class="card-header text-start">
          <h4 class="mb-0">Activation Details</h4>
        </div>
        <div class="card-body">
          <div class="row">
            <!-- License Information Column -->
            <div class="col-md-6">
              <div class="mb-3">
                @if(!env('APP_DEMO'))
                  <p class="mb-1 text-muted small">Activation Code</p>
                  <p class="mb-0 font-weight-bold">{{ $licenseInfo['activation']['activation_code'] }}</p>
                @endif
              </div>
              <div class="mb-3">
                <p class="mb-1 text-muted small">Licensed To</p>
                <p class="mb-0 font-weight-bold">{{ ucfirst($licenseInfo['activation']['email']) }}</p>
              </div>
              <div class="mb-3">
                <p class="mb-1 text-muted small">Activation Type</p>
                <p class="mb-0 font-weight-bold">{{ ucfirst($licenseInfo['activation']['activation_type']) }}</p>
              </div>
              <div class="mb-3">
                <p class="mb-1 text-muted small">Domain</p>
                <p class="mb-0 font-weight-bold">{{ $licenseInfo['activation']['domain'] }}</p>
              </div>
            </div>
            <!-- Activation Status Column -->
            <div class="col-md-6 mt-3">
              <div class="mb-3">
                <p class="mb-1 text-muted small">Status</p>
                <p class="mb-0 font-weight-bold">{{ ucfirst($licenseInfo['activation']['status']) }}</p>
              </div>
              <div class="mb-3">
                <p class="mb-1 text-muted small">Activated At</p>
                <p class="mb-0 font-weight-bold">
                  {{ \Carbon\Carbon::parse($licenseInfo['activation']['created_at'])->format('Y-m-d H:i') }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    @else
      @php
        $cacheKey = 'license_validity_' . config('app.url');
      \Illuminate\Support\Facades\Cache::store('file')->forget($cacheKey);
      @endphp
      @if(isset($licenseInfo) && $licenseInfo['code'] == 'inactive')
        <!-- Activation Disabled Contact Support Team Card -->
        <div class="card border-danger mb-4">
          <div class="card-header text-center">
            <h4 class="mb-0">Activation Disabled</h4>
          </div>
          <div class="card-body">
            <div class="text-center">
              <i class="bx bx-error-circle fs-1 mt-3"></i>
              <h5 class="mt-3">Activation is disabled for this domain.</h5>
              <p class="text-muted mb-0">
                Please contact support to enable activation for more info.
              </p>
            </div>
          </div>
        </div>
      @else
        <!-- Activation Pending Card -->
        <div class="card border-danger mb-4">
          <div class="card-header text-center">
            <h4 class="mb-0">Activation Pending</h4>
          </div>
          <div class="card-body">
            <div class="text-center">
              <i class="bx bx-error-circle fs-1 mt-3"></i>
              <h5 class="mt-3">Your copy of {{ config('variables.templateName') }} is not activated.</h5>
              <p class="text-muted">
                Please enter your purchase code to activate.
              </p>
            </div>
            <!-- Activation Form -->
            <form action="{{ route('activation.activate') }}" method="POST" class="mt-4">
              @csrf
              <div class="row justify-content-center">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="licenseKey" class="form-label">Purchase Code <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="licenseKey" name="licenseKey" placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" required>
                  </div>
                  <div class="mb-3">
                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="you@example.com" required>
                  </div>
                  <hr>
                  <div class="mb-3">
                    <label class="form-label">Optional: Envato Activation</label>
                    <small class="form-text text-muted">
                      Fill these fields if you are activating with an Envato license.
                    </small>
                  </div>
                  <div class="mb-3">
                    <label for="envatoUsername" class="form-label">Envato Username (Optional: Envato Activation)</label>
                    <input type="text" class="form-control" id="envatoUsername" name="envato_username" placeholder="Your Envato Username">
                  </div>
                  {{-- Optionally, you may include a hidden field for activation type if you want to switch between live and localhost --}}
                  <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Activate Now</button>
                  </div>
                </div>
              </div>
            </form>


          </div>
          @endif
        </div>
      @endif



        <!-- Helper area for activation -->
       <div class="p-5">
         <div class="alert alert-warning" role="alert">
           <h5 class="alert-heading">Activation Help</h5>
           <p> <a href="{{ config('variables.activationDocs') }}" target="_blank">Read the documentation</a> for more information on how to activate your copy of {{ config('variables.templateName') }}.</p>
           <p>
             If you are having trouble activating your copy of {{ config('variables.templateName') }}, please
             <a href="{{ config('variables.support') }}" target="_blank">contact support</a>.
           </p>
         </div>
       </div>
@endsection
