@php
  $configData = Helper::appClasses();
  $addonService = app(IAddonService::class);
  $isLocked = false; // System Unlocked per USER Request
@endphp

<aside id="layout-menu" class="hitech-sidebar layout-menu menu-vertical menu bg-menu-theme">
  
  {{-- 1. LOGO HUB --}}
  <div class="hitech-logo-area">
    <a href="{{url('/')}}" class="hitech-logo-link">
      <img src="{{ asset('assets/img/logo.png') }}" alt="HRX Logo" style="height: 38px; width: auto; object-fit: contain;">
      <div class="hitech-brand-meta">
        <span class="hitech-brand-text">Hi Tech <span class="brand-highlight">HRX</span></span>
        <span class="hitech-slogan">NEXT-GEN HRMS</span>
      </div>
    </a>
  </div>

  {{-- 2. SCROLLABLE MENU LIST --}}
  <div class="hitech-menu-container">
    <ul class="hitech-menu-list menu-inner py-1">
      @php
        // Determine which menu to show: SuperAdmin uses index 0, Tenant uses index 3
        $menuIndex = 3; 
        if (Auth::check() && Auth::user()->hasRole('super_admin')) {
            $menuIndex = 0;
        }
        $targetMenu = $menuData[$menuIndex]->menu ?? [];
      @endphp

      @foreach ($targetMenu as $menu)
        @php
          // 1. Addon & Visibility Logic
          if(isset($menu->addon) && !$addonService->isAddonEnabled($menu->addon)) continue;
          
          $user = auth()->user();
          $isAdmin = $user->hasRole(['admin', 'super_admin']);
          $isVisible = $isAdmin; 

          if (!$isVisible) {
              $hasRole = isset($menu->roles) ? $user->hasRole((array) $menu->roles) : true;
              $hasPerm = isset($menu->permission) ? $user->can($menu->permission) : true;
              $isVisible = $hasRole && $hasPerm;
          }

          if (!$isVisible) continue;
        @endphp

        {{-- 2. Rendering --}}
        @if (isset($menu->menuHeader))
          <li class="menu-header small text-uppercase py-3">
            <span class="menu-header-text text-muted" style="padding: 0 1.5rem; font-size: 0.75rem; font-weight: 600;">{{ __($menu->menuHeader) }}</span>
          </li>
        @else
          @php
            $activeClass = Route::currentRouteName() === ($menu->slug ?? '') ? 'active' : '';
            if (isset($menu->submenu)) {
                $subSlugs = (array) ($menu->slug ?? []);
                foreach($subSlugs as $slug) {
                    if (str_contains(Route::currentRouteName(), $slug)) {
                        $activeClass = 'active open';
                    }
                }
            }
          @endphp

          <li class="hitech-menu-item {{$activeClass}}">
            <a href="{{ isset($menu->url) ? url($menu->url) : 'javascript:void(0);' }}"
               class="hitech-menu-link {{ isset($menu->submenu) ? 'has-submenu' : '' }}">
              @isset($menu->icon)
                <i class="hitech-menu-icon {{ $menu->icon }}"></i>
              @endisset
              <span class="hitech-menu-text">{{ isset($menu->name) ? __($menu->name) : '' }}</span>
            </a>

            @isset($menu->submenu)
               <ul class="hitech-submenu">
                  @foreach($menu->submenu as $submenu)
                    @php
                      if(isset($submenu->addon) && !$addonService->isAddonEnabled($submenu->addon)) continue;
                      
                      $subVis = $isAdmin;
                      if (!$subVis) {
                          $sRole = isset($submenu->roles) ? $user->hasRole((array) $submenu->roles) : true;
                          $sPerm = isset($submenu->permission) ? $user->can($submenu->permission) : true;
                          $subVis = $sRole && $sPerm;
                      }
                      if (!$subVis) continue;
                    @endphp
                    <li class="hitech-submenu-item {{ Route::currentRouteName() === ($submenu->slug ?? '') ? 'active' : '' }}">
                       <a href="{{ url($submenu->url) }}" class="hitech-submenu-link">
                          {{ __($submenu->name) }}
                       </a>
                    </li>
                  @endforeach
               </ul>
            @endisset
          </li>
        @endif
      @endforeach
    </ul>
  </div>

  <div class="hitech-profile-card">
      <div class="hitech-profile-info">
        <div class="hitech-avatar">
          @if(auth()->user() && auth()->user()->getProfilePicture())
            <img src="{{ auth()->user()->getProfilePicture() }}" alt="Profile" class="w-100 h-100 rounded-circle object-fit-cover">
          @else
            <div class="avatar-initials bg-label-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; font-weight: 600;">
              {{ auth()->user() ? auth()->user()->getInitials() : 'US' }}
            </div>
          @endif
        </div>
        <div class="hitech-user-meta text-truncate" style="flex: 1; min-width: 0;">
          <span class="hitech-user-name fw-bold" style="display: block; font-size: 0.85rem;">{{ auth()->user() ? auth()->user()->name : 'User' }}</span>
          <a href="{{ route('employee.myProfile') }}" class="hitech-user-role-link">
            <span class="hitech-user-role text-muted small">{{ auth()->user() ? (auth()->user()->role_display_name) : 'Admin' }}</span>
          </a>
        </div>
        <div class="hitech-profile-actions">
           <form id="logout-form" action="{{ route('auth.logout') }}" method="POST" style="display: none;">
             @csrf
           </form>
           <a href="javascript:void(0);" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" title="Logout" class="text-danger opacity-75 hover-opacity-100">
             <i class="bx bx-log-out fs-5"></i>
           </a>
        </div>
      </div>
  </div>
</aside>
