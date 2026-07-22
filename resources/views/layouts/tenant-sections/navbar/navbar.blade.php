  @php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    $containerNav = ($configData['contentLayout'] === 'compact') ? 'container-xxl' : 'container-fluid';
    $navbarDetached = ($navbarDetached ?? '');
    $initial = Auth::user() ? Auth::user()->getInitials() : 'DU';
  @endphp

  <style>
    /* Sidebar toggle: a chevron that points the way the menu will move —
       left to hide, right to bring it back. */
    .hitech-menu-toggle { display: inline-flex; }
    .hitech-menu-toggle .hmt-btn {
        width: 40px; height: 40px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 12px;
        background: #f1f6f6;
        border: 1px solid rgba(13, 115, 119, .10);
        color: #0d7377;
        transition: background .18s ease, border-color .18s ease, box-shadow .18s ease, transform .12s ease, color .18s ease;
    }
    .hitech-menu-toggle:hover .hmt-btn {
        background: #0d7377; border-color: #0d7377; color: #fff;
        box-shadow: 0 6px 16px rgba(13, 115, 119, .28);
    }
    .hitech-menu-toggle:active .hmt-btn { transform: scale(.94); }
    .hmt-btn i { font-size: 1.35rem; line-height: 1; transition: transform .28s cubic-bezier(.4,0,.2,1); }
    /* Collapsed: flip the chevron to point outward */
    html.layout-menu-collapsed .hmt-btn i { transform: rotate(180deg); }
  </style>

  <nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme hitech-navbar" id="layout-navbar">
    <div class="container-fluid">

          <!-- Sidebar toggle. Keeps the .layout-menu-toggle hook the theme JS
               binds to; the icon reflects whether the rail is collapsed. -->
          @if(!isset($navbarHideToggle))
            <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0">
              <a class="nav-item nav-link px-0 hitech-menu-toggle" href="javascript:void(0)"
                 title="Collapse / expand menu" aria-label="Collapse or expand the menu">
                <span class="hmt-btn"><i class="bx bx-chevrons-left" aria-hidden="true"></i></span>
              </a>
            </div>
          @endif

          <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
            <!-- Page Title / Breadcrumb (Professional Minimalist) -->
            <div class="hitech-nav-title d-none d-md-flex align-items-center ms-2">
              <span class="text-muted opacity-50 me-2">Portal</span>
              <span class="bullet me-2">/</span>
              <h5 class="mb-0 fw-bold text-dark">@yield('title', 'Dashboard')</h5>
            </div>
            
            <!-- Custom Navbar Actions per page -->
            <div class="ms-3 d-flex align-items-center">
                @yield('navbar-actions')
            </div>

            <ul class="navbar-nav flex-row align-items-center ms-auto">
              <!-- Search icon moved to right -->
              @if($configData['displaySearch'] == true)
                <li class="nav-item me-2 me-xl-0">
                  <a class="nav-link search-toggler" href="javascript:void(0);">
                    <i class="bx bx-search bx-md"></i>
                  </a>
                </li>
              @endif
              @if(isset($menuHorizontal))
                <!-- Search -->
                @if($configData['displaySearch'] == true)
                  <li class="nav-item navbar-search-wrapper me-2 me-xl-0">
                    <a class="nav-link search-toggler" href="javascript:void(0);">
                      <i class="bx bx-search bx-md"></i>
                    </a>
                  </li>
                @endif
                <!-- /Search -->
              @endif
              {{-- Remove Quick Actions as per user request --}}
              {{-- @if($configData['displayQuickCreate'] == true)
                @include('layouts.sections.menu.quickCreateMenu')
              @endif --}}
              {{-- Addons removed as per user request --}}
              {{-- @if($configData['displayAddon'] == true)
                <li class="nav-item dropdown dropdown-addons me-2 me-xl-0">
                  <a class="nav-link dropdown-toggle hide-arrow" href="{{route('addons.index')}}">
                    <i data-bs-toggle="tooltip"
                       data-bs-placement="top"
                       title="@lang('Addons')" class="bx bx-category"></i>
                  </a>
                </li>
              @endif --}}
              <!-- Language -->
              @if($configData['displayLanguage'] == true)
                <li class="nav-item dropdown-language dropdown me-2 me-xl-0">
                  <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <i class=' bx bx-globe bx-md'></i>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a class="dropdown-item {{ app()->getLocale() === 'en' ? 'active' : '' }}"
                         href="{{url('lang/en')}}"
                         data-language="en" data-text-direction="ltr">
                        <span>English</span>
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item {{ app()->getLocale() === 'fr' ? 'active' : '' }}"
                         href="{{url('lang/fr')}}"
                         data-language="fr" data-text-direction="ltr">
                        <span>French</span>
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item {{ app()->getLocale() === 'ar' ? 'active' : '' }}"
                         href="{{url('lang/ar')}}"
                         data-language="ar" data-text-direction="rtl">
                        <span>Arabic</span>
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item {{ app()->getLocale() === 'de' ? 'active' : '' }}"
                         href="{{url('lang/de')}}"
                         data-language="de" data-text-direction="ltr">
                        <span>German</span>
                      </a>
                    </li>
                  </ul>
                </li>
              @endif
              <!-- /Language -->

              @if($configData['hasCustomizer'] == true)
                <!-- Style Switcher -->
                <li class="nav-item dropdown-style-switcher dropdown me-2 me-xl-0">
                  <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);"
                     data-bs-toggle="dropdown">
                    <i class='bx bx-md'></i>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end dropdown-styles">
                    <li>
                      <a class="dropdown-item" href="javascript:void(0);" data-theme="light">
                        <span><i class='bx bx-sun bx-md me-3'></i>Light</span>
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item" href="javascript:void(0);" data-theme="dark">
                        <span><i class="bx bx-moon bx-md me-3"></i>Dark</span>
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item" href="javascript:void(0);" data-theme="system">
                        <span><i class="bx bx-desktop bx-md me-3"></i>System</span>
                      </a>
                    </li>
                  </ul>
                </li>
                <!--/ Style Switcher -->
              @endif

              <!-- Quick links  -->
                {{-- Shortcuts removed as per user request --}}
              {{-- @if($configData['displayShortcut'] == true)
                <li class="nav-item dropdown-shortcuts navbar-dropdown dropdown me-2 me-xl-0">
                  ...
                </li>
              @endif --}}
              <!-- Quick links -->

              <!-- Notification -->
              @if($configData['displayNotification'] == true)
                @include('layouts.sections.navbar.notifications')
              @endif
              <!--/ Notification -->

              <li class="nav-item navbar-dropdown dropdown-user dropdown ms-0">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                  <div class="hitech-user-switch">
                    <div class="switch-selector">
                        @php $profilePic = Auth::user() ? Auth::user()->getProfilePicture() : null; @endphp
                        @if($profilePic)
                          <img src="{{ $profilePic }}" alt class="w-px-38 h-auto rounded-circle">
                        @else
                          {{ $initial }}
                        @endif
                    </div>
                    <div class="switch-content d-none d-md-flex">
                        <span class="name">{{ Auth::user() ? Auth::user()->getFullName() : 'Demo User' }}</span>
                    </div>
                  </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end hitech-dropdown-menu animate__animated animate__fadeIn">
                  <li>
                    <div class="hitech-dropdown-user-header">
                        <div class="avatar avatar-online">
                            @if($profilePic)
                              <img src="{{ $profilePic }}" alt class="w-px-40 h-auto rounded-circle">
                            @else
                              <span class="avatar-initial rounded-circle bg-label-secondary">{{ Auth::user() ? Auth::user()->getInitials() : 'DU' }}</span>
                            @endif
                        </div>
                        <div class="user-info">
                            <span class="name">{{ Auth::user() ? Auth::user()->getFullName() : 'Demo User' }}</span>
                        </div>
                    </div>
                  </li>
                  <li>
                    <a class="dropdown-item" href="{{ route('account.myProfile') }}">
                      <i class="bx bx-user-circle"></i><span>@lang('My Profile')</span>
                    </a>
                  </li>
                  <li>
                    <a role="button" class="dropdown-item" data-bs-target="#changePasswordModal" data-bs-toggle="modal">
                      <i class="bx bx-shield"></i><span>@lang('Change Password')</span>
                    </a>
                  </li>
                  <li>
                    <div class="dropdown-divider"></div>
                  </li>
                  @if (Auth::check())
                    <li>
                      <a class="dropdown-item logout-item" href="{{ route('auth.logout') }}"
                         onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class='bx bx-log-out'></i><span>@lang('Logout')</span>
                      </a>
                    </li>
                    <form method="POST" id="logout-form" action="{{ route('auth.logout') }}">
                      @csrf
                    </form>
                  @else
                    <li>
                      <a class="dropdown-item"
                         href="{{ Route::has('login') ? route('auth.login') : url('auth/login-basic') }}">
                        <i class='bx bx-log-in-circle'></i><span>@lang('Login')</span>
                      </a>
                    </li>
                  @endif
                </ul>
              </li>
              <!--/ User -->
            </ul>
          </div>

          <!-- Search Small Screens -->
          <div
            class="navbar-search-wrapper search-input-wrapper {{ isset($menuHorizontal) ? $containerNav : '' }} d-none">
            <input type="text"
                   class="form-control search-input {{ isset($menuHorizontal) ? '' : $containerNav }} border-0"
                   placeholder="@lang('Search...')" aria-label="Search...">
            <i class="bx bx-x bx-md search-toggler cursor-pointer"></i>
          </div>
          @if(isset($navbarDetached) && $navbarDetached == '')
        </div>
        @endif
      </nav>
      <!-- / Navbar -->
