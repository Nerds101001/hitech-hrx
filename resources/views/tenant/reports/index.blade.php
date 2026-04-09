@php
  use App\Services\AddonService\IAddonService;$title = 'Reports';

  $addonService = app(IAddonService::class);

$reports = ['Attendance'];
 $reports[] = 'Leave';
  $reports[] = 'Expense';
   $reports[] = 'Visit';

if($addonService->isAddonEnabled(ModuleConstants::PRODUCT_ORDER)){
    $reports[] = 'ProductOrder';
}


@endphp

@extends('layouts/layoutMaster')

@section('title', __($title))

@section('vendor-style')
    @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss', 'resources/assets/vendor/libs/animate-css/animate.scss'])
@endsection

@section('content')
  <div class="hitech-page-hero mb-6">
    <div class="row align-items-center">
      <div class="col-sm-6">
        <h4 class="text-white mb-1 animate__animated animate__fadeInLeft">
          <i class="bx bx-bar-chart-alt-2 me-2"></i>{{$title}}
        </h4>
        <p class="text-white opacity-75 mb-0 animate__animated animate__fadeInLeft" style="animation-delay: 0.1s">
          Generate and download system reports
        </p>
      </div>
      <div class="col-sm-6 text-sm-end mt-3 mt-sm-0 animate__animated animate__fadeInRight">
        <span class="badge bg-label-primary badge-hitech">
          <i class="bx bx-time-five me-1"></i>Last Updated: {{ now()->format('M d, Y') }}
        </span>
      </div>
    </div>
  </div>

  <div class="row justify-content-start animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
    @foreach($reports as $report)
      @php
        $icon = match($report) {
            'Attendance' => 'bx-calendar-check',
            'Leave' => 'bx-calendar-x',
            'Expense' => 'bx-money',
            'Visit' => 'bx-map-pin',
            'ProductOrder' => 'bx-shopping-bag',
            default => 'bx-file'
        };
        $color = match($report) {
            'Attendance' => 'success',
            'Leave' => 'danger',
            'Expense' => 'warning',
            'Visit' => 'primary',
            'ProductOrder' => 'info',
            default => 'secondary'
        };
      @endphp
      <div class="col-sm-6 col-lg-4 col-xl-3 mb-6">
        <div class="hitech-card h-100 group-hover-effect overflow-hidden">
          {{-- Decorative Background Icon --}}
          <i class="bx {{ $icon }} position-absolute" style="right: -10px; bottom: -10px; font-size: 5rem; opacity: 0.05; transform: rotate(-15deg);"></i>
          
          <div class="hitech-card-header d-flex align-items-center border-bottom border-light border-opacity-10 pb-4">
            <div class="stat-icon-wrap icon-{{ $color }} me-3" style="width: 40px; height: 40px; min-width: 40px;">
              <i class="bx {{ $icon }} fs-4"></i>
            </div>
            <h5 class="card-title mb-0 text-white fw-bold">{{ $report }} Report</h5>
          </div>
          
          <div class="card-body d-flex flex-column pt-5">
            <form action="{{ route('report.get'. $report . 'Report') }}" method="post" class="mt-auto position-relative z-index-1">
              @csrf
              <div class="form-group mb-4">
                <label for="period_{{ $report }}" class="form-label-hitech opacity-75 mb-2">Select Month & Year</label>
                <div class="input-group input-group-merge shadow-none">
                  <span class="input-group-text bg-transparent border-light border-opacity-10 text-white"><i class="bx bx-calendar"></i></span>
                  <input type="month" class="form-control form-control-hitech border-light border-opacity-10" id="period_{{ $report }}" name="period" required/>
                </div>
              </div>
              
              <button type="submit" class="btn btn-{{ $color }} btn-hitech-glow w-100 d-flex align-items-center justify-content-center py-2">
                <i class="bx bx-download me-2"></i>Generate Report
              </button>
            </form>
          </div>
        </div>
      </div>
    @endforeach
  </div>
@endsection
