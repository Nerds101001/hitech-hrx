@isset($pageConfigs)
  {!! Helper::updatePageConfig($pageConfigs) !!}
@endisset
@php
  $configData = Helper::appClasses();
@endphp
@extends('layouts/commonMaster' )

@php
  /* Display elements */
  $contentNavbar = ($contentNavbar ?? true);
  $containerNav = ($containerNav ?? 'container-xxl');
  $isNavbar = ($isNavbar ?? true);
  $isMenu = ($isMenu ?? true);
  $isFlex = ($isFlex ?? false);
  $isFooter = ($isFooter ?? true);
  $customizerHidden = ($customizerHidden ?? '');

  /* HTML Classes */
  $navbarDetached = 'navbar-detached';
  $menuFixed = (isset($configData['menuFixed']) ? $configData['menuFixed'] : '');
  if(isset($navbarType)) {
    $configData['navbarType'] = $navbarType;
  }
  $navbarType = (isset($configData['navbarType']) ? $configData['navbarType'] : '');
  $footerFixed = (isset($configData['footerFixed']) ? $configData['footerFixed'] : '');
  $menuCollapsed = (isset($configData['menuCollapsed']) ? $configData['menuCollapsed'] : '');

  /* Content classes */
  $container = (isset($configData['contentLayout']) && $configData['contentLayout'] === 'compact') ? 'container-xxl' : 'container-fluid';

@endphp

@section('layoutContent')
  <div class="layout-wrapper layout-content-navbar {{ $isMenu ? '' : 'layout-without-menu' }}">
    <div class="layout-container">

      @if ($isMenu)
        @include('layouts/tenant-sections/menu/verticalMenu')
      @endif


      <!-- Layout page -->
      <div class="layout-page">

        {{-- Below commented code read by artisan command while installing jetstream. !! Do not remove if you want to use jetstream. --}}
        {{-- <x-banner /> --}}

        <!-- BEGIN: Navbar-->
        @if ($isNavbar)
          @include('layouts/sections/navbar/navbar')
        @endif
        <!-- END: Navbar-->


        <!-- Content wrapper -->
        <div class="content-wrapper">

          <!-- Content -->
          @if ($isFlex)
            <div class="{{$container}} d-flex align-items-stretch flex-grow-1 p-0">
              @else
                <div class="{{$container}} flex-grow-1 container-p-y">
                  @endif

                  @if(auth()->check() && auth()->user()->status === \App\Enums\UserAccountStatus::ONBOARDING_SUBMITTED)
                    <div class="alert alert-warning hitech-note-card animate__animated animate__fadeIn m-4" role="alert">
                      <div class="d-flex align-items-center">
                        <i class="bx bx-time-five fs-4 me-3"></i>
                        <div>
                          <strong>Profile Under Review:</strong> Your documents are being verified by HR. Dashboard access is currently restricted.
                        </div>
                        <a href="{{ route('onboarding.status') }}" class="btn btn-sm btn-warning ms-auto">Check Status</a>
                      </div>
                    </div>
                  @endif

                  @yield('content')

                </div>
                <!-- / Content -->

                <!-- Footer -->
                @if ($isFooter)
                  @include('layouts/tenant-sections/footer/footer')
                @endif
                <!-- / Footer -->
                <div class="content-backdrop fade"></div>
            </div>
            <!--/ Content wrapper -->
        </div>
        <!-- / Layout page -->
      </div>

      @if ($isMenu)
        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
      @endif
      <!-- Drag Target Area To SlideIn Menu On Small Screens -->
      <div class="drag-target"></div>
    </div>
    <!-- / Layout wrapper -->
@endsection
