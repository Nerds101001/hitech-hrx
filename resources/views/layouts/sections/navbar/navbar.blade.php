@php
    use App\Models\SOSLog;
    use App\Services\AddonService\IAddonService;
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Route;
    $containerNav = $configData['contentLayout'] === 'compact' ? 'container-xxl' : 'container-fluid';
    $navbarDetached = $navbarDetached ?? '';
    $initial = Auth::user()->getInitials();
    $addonService = app(IAddonService::class);
    $isSuperAdmin = Auth::user()->hasRole('admin');
@endphp

<!-- Navbar -->
<nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme hitech-navbar" id="layout-navbar">
    <div class="container-fluid">

<!-- ! Menu toggle for mobile -->
@if (!isset($navbarHideToggle))
    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0">
        <a class="nav-item nav-link px-0" href="javascript:void(0)">
            <i class="bx bx-menu bx-md"></i>
        </a>
    </div>
@endif

<div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
    <!-- Page Title / Breadcrumb (Minimalist Awesome Design) -->
    <div class="hitech-nav-title d-none d-md-flex">
      <h4 class="mb-0 fw-bold">@yield('title', config('variables.templateName'))</h4>
    </div>

    <ul class="navbar-nav flex-row align-items-center ms-auto">
        <!-- Search icon moved to right -->
        @if ($configData['displaySearch'] == true)
          <li class="nav-item me-2 me-xl-0">
            <a class="nav-link search-toggler" href="javascript:void(0);">
              <i class="bx bx-search bx-md"></i>
            </a>
          </li>
        @endif
        @if (isset($menuHorizontal))
            <!-- Search -->
            @if ($configData['displaySearch'] == true)
                <li class="nav-item navbar-search-wrapper me-2 me-xl-0">
                    <a class="nav-link search-toggler" href="javascript:void(0);">
                        <i class="bx bx-search bx-md"></i>
                    </a>
                </li>
            @endif
            <!-- /Search -->
        @endif

        @if (auth()->user()->roles()->first()->name != 'super_admin')
            @if ($addonService->isAddonEnabled(ModuleConstants::SOS, true))
                <!-- SOS Requests -->
                <li class="nav-item dropdown dropdown-sos me-2 me-xl-0">
                    <a class="nav-link dropdown-toggle hide-arrow" href="{{ route('sos.index') }}">
                        <i data-bs-toggle="tooltip" data-bs-placement="top" title="@lang('SOS Requests')"
                            class="bx bx-bullseye"></i>
                        {{-- <div
                        class="badge bg-danger rounded-pill ms-auto">{{SOSLog::where('status','pending')->count()}}</div> --}}
                    </a>
                </li>
            @endif

              @if ($addonService->isAddonEnabled(ModuleConstants::CALENDAR))
                <!-- Calendar — only render if route exists -->
                @if(\Illuminate\Support\Facades\Route::has('calendar.index'))
                <li class="nav-item dropdown dropdown-calendar me-2 me-xl-0">
                    <a class="nav-link dropdown-toggle hide-arrow" href="{{ route('calendar.index') }}">
                        <i data-bs-toggle="tooltip" data-bs-placement="top" title="@lang('Calendar')"
                            class="bx bx-calendar"></i>
                    </a>
                </li>
                @endif
              @endif

            @if ($addonService->isAddonEnabled(ModuleConstants::AI_CHATBOT))
                <!-- AI Chat -->
                <li class="nav-item dropdown dropdown-addons me-2 me-xl-0">
                    <a class="nav-link dropdown-toggle hide-arrow" href="{{ route('aiChat.index') }}">

                        <i data-bs-toggle="tooltip" data-bs-placement="top" title="@lang('Ai Chat')"
                            class="bx bx-chat"></i>
                    </a>
                </li>
            @endif

              @if ($addonService->isAddonEnabled(ModuleConstants::NOTES))
                <!-- Notes -->
                <li class="nav-item dropdown dropdown-addons me-2 me-xl-0">
                    <a class="nav-link dropdown-toggle hide-arrow" href="{{ route('user.notes.index') }}">
                        <i data-bs-toggle="tooltip" data-bs-placement="top" title="@lang('Notes')"
                            class="bx bx-notepad"></i>
                    </a>
                </li>
              @endif
        @endif

        {{-- Remove Quick Actions as per user request --}}
        {{-- @if ($configData['displayQuickCreate'] == true)
            @include('layouts.sections.menu.quickCreateMenu')
        @endif --}}
        @if ($configData['displayAddon'] == true && $isSuperAdmin)
            <!--Addons -->
            <li class="nav-item dropdown dropdown-addons me-2 me-xl-0">
                <a class="nav-link dropdown-toggle hide-arrow" href="{{ route('addons.index') }}">
                    <i data-bs-toggle="tooltip" data-bs-placement="top" title="@lang('Addons')"
                        class="bx bx-category"></i>
                </a>
            </li>
        @endif
          @if ($isSuperAdmin)
        <!-- Settings -->
        <li class="nav-item dropdown dropdown-addons me-2 me-xl-0">
            <a class="nav-link dropdown-toggle hide-arrow" href="{{ route('settings.index') }}">
                <i data-bs-toggle="tooltip" data-bs-placement="top" title="@lang('Settings')"
                    class="tf-icons bx bx-cog"></i>
            </a>
        </li>
          @endif
        <!-- Language -->
        @if ($configData['displayLanguage'] == true)
            <li class="nav-item dropdown-language dropdown me-2 me-xl-0">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <i class=' bx bx-globe bx-md'></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item {{ app()->getLocale() === 'en' ? 'active' : '' }}"
                            href="{{ url('lang/en') }}" data-language="en" data-text-direction="ltr">
                            <span>English</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item {{ app()->getLocale() === 'fr' ? 'active' : '' }}"
                            href="{{ url('lang/fr') }}" data-language="fr" data-text-direction="ltr">
                            <span>French</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item {{ app()->getLocale() === 'ar' ? 'active' : '' }}"
                            href="{{ url('lang/ar') }}" data-language="ar" data-text-direction="rtl">
                            <span>Arabic</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item {{ app()->getLocale() === 'de' ? 'active' : '' }}"
                            href="{{ url('lang/de') }}" data-language="de" data-text-direction="ltr">
                            <span>German</span>
                        </a>
                    </li>
                </ul>
            </li>
        @endif
        <!-- /Language -->

        @if ($configData['hasCustomizer'] == true)
            <!-- Style Switcher -->
            <li class="nav-item dropdown-style-switcher dropdown me-2 me-xl-0">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
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
        @if ($configData['displayShortcut'] == true)
            <li class="nav-item dropdown-shortcuts navbar-dropdown dropdown me-2 me-xl-0">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown"
                    data-bs-auto-close="outside" aria-expanded="false">
                    <i class='bx bx-grid-alt bx-md'></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end p-0">
                    <div class="dropdown-menu-header border-bottom">
                        <div class="dropdown-header d-flex align-items-center py-3">
                            <h6 class="mb-0 me-auto">Shortcuts</h6>
                            <a href="javascript:void(0)" class="dropdown-shortcuts-add py-2" data-bs-toggle="tooltip"
                                data-bs-placement="top" title="Add shortcuts"><i
                                    class="bx bx-plus-circle text-heading"></i></a>
                        </div>
                    </div>
                    <div class="dropdown-shortcuts-list scrollable-container">
                        <div class="row row-bordered overflow-visible g-0">
                            <div class="dropdown-shortcuts-item col">
                                <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                                    <i class="bx bx-calendar bx-26px text-heading"></i>
                                </span>
                                <a href="{{ url('app/calendar') }}" class="stretched-link">Calendar</a>
                                <small>Appointments</small>
                            </div>
                            <div class="dropdown-shortcuts-item col">
                                <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                                    <i class="bx bx-food-menu bx-26px text-heading"></i>
                                </span>
                                <a href="{{ url('app/invoice/list') }}" class="stretched-link">Invoice App</a>
                                <small>Manage Accounts</small>
                            </div>
                        </div>
                        <div class="row row-bordered overflow-visible g-0">
                            <div class="dropdown-shortcuts-item col">
                                <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                                    <i class="bx bx-user bx-26px text-heading"></i>
                                </span>
                                <a href="{{ url('app/user/list') }}" class="stretched-link">User App</a>
                                <small>Manage Users</small>
                            </div>
                            <div class="dropdown-shortcuts-item col">
                                <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                                    <i class="bx bx-check-shield bx-26px text-heading"></i>
                                </span>
                                <a href="{{ url('app/access-roles') }}" class="stretched-link">Role Management</a>
                                <small>Permission</small>
                            </div>
                        </div>
                        <div class="row row-bordered overflow-visible g-0">
                            <div class="dropdown-shortcuts-item col">
                                <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                                    <i class="bx bx-pie-chart-alt-2 bx-26px text-heading"></i>
                                </span>
                                <a href="{{ url('/') }}" class="stretched-link">Dashboard</a>
                                <small>User Dashboard</small>
                            </div>
                            <div class="dropdown-shortcuts-item col">
                                <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                                    <i class="bx bx-cog bx-26px text-heading"></i>
                                </span>
                                <a href="{{ url('pages/account-settings-account') }}"
                                    class="stretched-link">Setting</a>
                                <small>Account Settings</small>
                            </div>
                        </div>
                        <div class="row row-bordered overflow-visible g-0">
                            <div class="dropdown-shortcuts-item col">
                                <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                                    <i class="bx bx-help-circle bx-26px text-heading"></i>
                                </span>
                                <a href="{{ url('pages/faq') }}" class="stretched-link">FAQs</a>
                                <small>FAQs & Articles</small>
                            </div>
                            <div class="dropdown-shortcuts-item col">
                                <span class="dropdown-shortcuts-icon rounded-circle mb-3">
                                    <i class="bx bx-window-open bx-26px text-heading"></i>
                                </span>
                                <a href="{{ url('modal-examples') }}" class="stretched-link">Modals</a>
                                <small>Useful Popups</small>
                            </div>
                        </div>
                    </div>
                </div>
            </li>
        @endif
        <!-- Quick links -->

        <!-- Notification -->
        @if ($configData['displayNotification'] == true && auth()->user()->roles()->first()->name != 'super_admin')
            @include('layouts.sections.navbar.notifications')
        @endif
        <!--/ Notification -->

        <li class="nav-item navbar-dropdown dropdown-user dropdown ms-0">
            <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                <div class="hitech-user-switch">
                    <div class="switch-selector">
                        @if (Auth::user() && Auth::user()->getProfilePicture())
                            <img src="{{ Auth::user()->getProfilePicture() }}" alt class="w-100 h-100 rounded-circle object-fit-cover">
                        @else
                            {{ $initial }}
                        @endif
                    </div>
                    <div class="switch-content d-none d-md-flex">
                        <span class="name">{{ Auth::user() ? Auth::user()->getFullName() : 'Demo User' }}</span>
                        <span class="role">{{ Auth::user() ? Auth::user()->roles()->first()->name : 'Employee' }}</span>
                    </div>
                    <i class="bx bx-chevron-down hitech-user-chevron"></i>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end hitech-dropdown-menu animate__animated animate__fadeIn">
                <li>
                  <div class="hitech-dropdown-user-header">
                      <div class="avatar avatar-online">
                          @if (Auth::user() && Auth::user()->getProfilePicture())
                              <img src="{{ Auth::user()->getProfilePicture() }}" alt class="w-px-40 h-auto rounded-circle">
                          @else
                              <span class="avatar-initial rounded-circle bg-label-primary">{{ $initial }}</span>
                          @endif
                      </div>
                      <div class="user-info">
                          <span class="name">{{ Auth::user()->getFullName() }}</span>
                          <span class="role">{{ Auth::user()->roles()->first()->name }}</span>
                      </div>
                  </div>
                </li>
                <li>
                    <a class="dropdown-item"
                        href="{{ auth()->user()->hasRole('super_admin') ? route('account.myProfile') : route('employee.myProfile') }}">
                        <i class="bx bx-user-circle"></i><span>@lang('My Profile')</span>
                    </a>
                </li>
                <li>
                    <a role="button" class="dropdown-item" data-bs-target="#changePasswordModal"
                        data-bs-toggle="modal">
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
<div class="navbar-search-wrapper search-input-wrapper {{ isset($menuHorizontal) ? $containerNav : '' }} d-none">
    <input type="text"
        class="form-control search-input {{ isset($menuHorizontal) ? '' : $containerNav }} border-0"
        placeholder="@lang('Search...')" aria-label="Search...">
    <i class="bx bx-x bx-md search-toggler cursor-pointer"></i>
</div>
@if (isset($navbarDetached) && $navbarDetached == '')
    </div>
@endif
</nav>

<!-- / Navbar -->
