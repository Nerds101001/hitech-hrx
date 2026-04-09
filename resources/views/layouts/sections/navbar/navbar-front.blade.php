@php
  use Illuminate\Support\Facades\Auth;
  use Illuminate\Support\Facades\Route;
$currentRouteName = Route::currentRouteName();
$activeRoutes = ['front-pages-pricing', 'front-pages-payment', 'front-pages-checkout', 'front-pages-help-center'];
$activeClass = in_array($currentRouteName, $activeRoutes) ? 'active' : '';
@endphp
  <!-- Navbar: Start -->
<nav class="layout-navbar shadow-none py-0">
  <div class="container">
    <div class="navbar navbar-expand-lg landing-navbar px-3 px-md-8">
      <!-- Menu logo wrapper: Start -->
      <div class="navbar-brand app-brand demo d-flex py-0 me-4 me-xl-8">
        <!-- Mobile menu toggle: Start-->
        <button class="navbar-toggler border-0 px-0 me-4" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
                aria-label="Toggle navigation">
          <i class="tf-icons bx bx-menu bx-lg align-middle text-heading fw-medium"></i>
        </button>
        <!-- Mobile menu toggle: End-->
        <a href="javascript:" class="app-brand-link">
          <span
            class="app-brand-logo demo">
            <img src="{{asset('assets/img/logo.png')}}" alt="Logo" width="27">
          </span>
          <span class="app-brand-text demo menu-text fw-bold ms-2 ps-1">{{config('variables.templateName')}}</span>
        </a>
      </div>
      <!-- Menu logo wrapper: End -->
      <!-- Menu wrapper: Start -->
      <div class="collapse navbar-collapse landing-nav-menu" id="navbarSupportedContent">
        <button class="navbar-toggler border-0 text-heading position-absolute end-0 top-0 scaleX-n1-rtl p-2"
                type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
                aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
          <i class="tf-icons bx bx-x bx-lg"></i>
        </button>
      </div>
      <div class="landing-menu-overlay d-lg-none"></div>
      <!-- Menu wrapper: End -->
      <!-- Toolbar: Start -->
      <ul class="navbar-nav flex-row align-items-center ms-auto">
        @if($configData['hasCustomizer'] == true)
          <!-- Style Switcher -->
          <li class="nav-item dropdown-style-switcher dropdown me-2 me-xl-1">
            <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
              <i class='bx bx-lg'></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end dropdown-styles">
              <li>
                <a class="dropdown-item" href="javascript:void(0);" data-theme="light">
                  <span class="align-middle"><i class='bx bx-md bx-sun me-3'></i>Light</span>
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="javascript:void(0);" data-theme="dark">
                  <span class="align-middle"><i class="bx bx-md bx-moon me-3"></i>Dark</span>
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="javascript:void(0);" data-theme="system">
                  <span class="align-middle"><i class="bx bx-md bx-desktop me-3"></i>System</span>
                </a>
              </li>
            </ul>
          </li>
          <!-- / Style Switcher-->
        @endif
        <!-- navbar button: Start -->
        @if(Auth::check())
          @if(Auth::user()->hasRole('super_admin'))
            <li>
              <a href="{{route('superAdmin.dashboard')}}" class="btn btn-primary me-2"><span
                  class="tf-icons bx bx-user-circle scaleX-n1-rtl me-md-1"></span><span
                  class="d-none d-md-block">Dashboard</span></a>
            </li>
          @endif
          <!-- User -->
          <li class="nav-item navbar-dropdown dropdown-user dropdown">
            <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);"
               data-bs-toggle="dropdown">
              <div class="avatar avatar-online">
                @if (Auth::user() && Auth::user()->getProfilePicture())
                  <img src="{{ Auth::user()->getProfilePicture() }}" alt class="w-px-40 h-auto rounded-circle shadow-sm" style="object-fit: cover;">
                @else
                  <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=127464&color=fff" alt class="w-px-40 h-auto rounded-circle">
                @endif
              </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li>
                <a class="dropdown-item"
                   href="#">
                  <div class="d-flex">
                    <div class="flex-shrink-0 me-3">
                      <div class="avatar avatar-online">
                        @if (Auth::user() && Auth::user()->getProfilePicture())
                          <img src="{{ Auth::user()->getProfilePicture() }}" alt class="w-px-40 h-auto rounded-circle shadow-sm" style="object-fit: cover;">
                        @else
                          <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=127464&color=fff" alt class="w-px-40 h-auto rounded-circle">
                        @endif
                      </div>
                    </div>
                    <div class="flex-grow-1">
                      <h6 class="mb-0">
                        {{Auth::user()->getFullName()}}
                      </h6>
                      <small class="text-muted">{{Auth::user()->roles()->first()->name}}</small>
                    </div>
                  </div>
                </a>
              </li>
              <li>
                <div class="dropdown-divider my-1"></div>
              </li>
              <li>
                <a role="button" class="dropdown-item" data-bs-target="#changePasswordModal"
                   data-bs-toggle="modal">
                <span class="d-flex align-items-center align-middle">
                  <i class="flex-shrink-0 bx bx-lock bx-md me-3"></i><span
                    class="flex-grow-1 align-middle">@lang('Change Password')</span>
                </span>
                </a>
              </li>
              <li>
                <div class="dropdown-divider my-1"></div>
              </li>
              @if (Auth::check())
                <li>
                  <a class="dropdown-item" href="{{ route('auth.logout') }}"
                     onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <i class='bx bx-power-off bx-md me-3'></i><span>@lang('Logout')</span>
                  </a>
                </li>
                <form method="POST" id="logout-form" action="{{ route('auth.logout') }}">
                  @csrf
                </form>
              @else
                <li>
                  <a class="dropdown-item"
                     href="{{ Route::has('login') ? route('auth.login') : url('auth/login-basic') }}">
                    <i class='bx bx-log-in bx-md me-3'></i><span>@lang('Login')</span>
                  </a>
                </li>
              @endif
            </ul>
          </li>
          <!--/ User -->
        @else
          <a href="{{route('auth.login')}}" class="btn btn-primary" target="_blank"><span
              class="tf-icons bx bx-log-in-circle scaleX-n1-rtl me-md-1"></span><span
              class="d-none d-md-block">Login</span></a>
          <a href="{{route('auth.register')}}" class="btn btn-primary ms-2" target="_blank"><span
              class="tf-icons bx bx-user-plus scaleX-n1-rtl me-md-1"></span><span
              class="d-none d-md-block">Register</span></a>
        @endif
        <!-- navbar button: End -->
      </ul>
      <!-- Toolbar: End -->
    </div>
  </div>
</nav>
<!-- Navbar: End -->
