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
          // 1. Addon Check
          if(isset($menu->addon) && !$addonService->isAddonEnabled($menu->addon)) continue;
          
          // 2. Identify User & Admin Status (Robust Check)
          $user = auth()->user();
          $userRoles = array_map('strtolower', $user->roles->pluck('name')->toArray());
          $isAdmin = !empty(array_intersect($userRoles, ['admin', 'super_admin']));
          
          // 3. Visibility Check
          $isVisible = $isAdmin; // Admins see everything by default
          
          if (!$isVisible) {
              $hasRole = true;
              $hasPermission = true;

              // Check Roles
              if (isset($menu->roles)) {
                  $requiredRoles = array_map('strtolower', (array) $menu->roles);
                  // Map 'employee' in JSON to 'field_employee' in DB if needed
                  if (in_array('employee', $requiredRoles) && !in_array('field_employee', $requiredRoles)) {
                      $requiredRoles[] = 'field_employee';
                  }
                  $hasRole = !empty(array_intersect($userRoles, $requiredRoles));
              }

              // Check Permissions
              if (isset($menu->permission)) {
                  $hasPermission = $user->can($menu->permission);
              }

              // Item is visible if it satisfies both if they are set
              $isVisible = $hasRole && $hasPermission;
          }

          if (!$isVisible) continue;
        @endphp

        @if (isset($menu->menuHeader))
          <li class="menu-header small text-uppercase">
            <span class="menu-header-text">{{ __($menu->menuHeader) }}</span>
          </li>
        @else
          @php
            $activeClass = '';
            $currentRouteName = Route::currentRouteName();
            if ($currentRouteName === ($menu->slug ?? '')) {
              $activeClass = 'active';
            } elseif (isset($menu->submenu)) {
              if (gettype($menu->slug) === 'array') {
                foreach($menu->slug as $slug) {
                  if (str_contains($currentRouteName,$slug) && strpos($currentRouteName,$slug) === 0) {
                    $activeClass = 'active open';
                  }
                }
              } else {
                if (str_contains($currentRouteName,($menu->slug ?? '')) && strpos($currentRouteName,($menu->slug ?? '')) === 0) {
                  $activeClass = 'active open';
                }
              }
            }
          @endphp
          <li class="hitech-menu-item {{$activeClass}}">
            <a href="{{ $isLocked ? 'javascript:void(0);' : (isset($menu->url) ? url($menu->url) : 'javascript:void(0);') }}"
               class="hitech-menu-link {{ isset($menu->submenu) ? 'has-submenu' : '' }} {{ $isLocked ? 'hitech-menu-locked' : '' }}">
              @isset($menu->icon)
                <i class="hitech-menu-icon {{ $menu->icon }}"></i>
              @endisset
              <span class="hitech-menu-text">{{ isset($menu->name) ? __($menu->name) : '' }}</span>
              @if($isLocked)
                 <i class="bx bx-lock-alt ms-auto menu-lock-icon"></i>
              @endif
            </a>
            @isset($menu->submenu)
               <ul class="hitech-submenu">
                  @foreach($menu->submenu as $submenu)
                     @php
                      // Submenu Role/Addon check
                      if(isset($submenu->addon) && !$addonService->isAddonEnabled($submenu->addon)) continue;
                      
                      $subVisible = $isAdmin;
                      if (!$subVisible) {
                          $subRole = true;
                          $subPerm = true;
                          
                          if (isset($submenu->roles)) {
                              $reqSubRoles = array_map('strtolower', (array) $submenu->roles);
                              if (in_array('employee', $reqSubRoles) && !in_array('field_employee', $reqSubRoles)) {
                                  $reqSubRoles[] = 'field_employee';
                              }
                              $subRole = !empty(array_intersect($userRoles, $reqSubRoles));
                          }
                          
                          if (isset($submenu->permission)) {
                              $subPerm = $user->can($submenu->permission);
                          }
                          
                          $subVisible = $subRole && $subPerm;
                      }
                      
                      if (!$subVisible) continue;
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

  {{-- 3. FLOATING PROFILE CARD --}}
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
        <div class="hitech-user-meta">
          <span class="hitech-user-name">{{ auth()->user() ? auth()->user()->name : 'User' }}</span>
          <a href="{{ route('employee.myProfile') }}" class="hitech-user-role-link">
            <span class="hitech-user-role text-muted small">{{ auth()->user() ? (auth()->user()->role_display_name) : 'Admin' }}</span>
          </a>
        </div>
        <div class="hitech-profile-actions">
           <form id="logout-form" action="{{ route('auth.logout') }}" method="POST" style="display: none;">
             @csrf
           </form>
           <a href="javascript:void(0);" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" title="Logout">
             <i class="bx bx-log-out"></i>
           </a>
        </div>
      </div>
  </div>

</aside>
