@php
  use App\Services\AddonService\IAddonService;
  use Illuminate\Support\Facades\Route;
  $configData = Helper::appClasses();
  $addonService = app(IAddonService::class);
  $isLocked = auth()->check() && auth()->user()->status === \App\Enums\UserAccountStatus::ONBOARDING_SUBMITTED;
@endphp

{{-- 1. SIDEBAR CONTAINER --}}
<aside id="layout-menu" class="hitech-sidebar layout-menu menu-vertical menu" style="background-color: #ffffff !important;">

  {{-- 1. LOGO HUB --}}
  <div class="hitech-logo-area d-flex align-items-center justify-content-start px-4" style="height: 70px; background: #ffffff !important; border-bottom: 1px solid rgba(0,0,0,0.05);">
    <a href="{{url('/')}}" class="hitech-logo-link d-flex align-items-center">
      <img src="{{ asset('assets/img/logo.png') }}" alt="HRX Logo" style="max-height: 45px; max-width: 160px; width: auto; object-fit: contain;">
    </a>
  </div>

  {{-- 2. SCROLLABLE MENU LIST --}}
  <div class="hitech-menu-container">
    <ul class="hitech-menu-list menu-inner py-1">
      @php
        // Determine which menu to show: SuperAdmin uses index 0, Tenant uses index 3
        $menuIndex = 3; 
        if (auth()->check() && auth()->user()->hasRole('super_admin')) {
            $menuIndex = 0;
        }
        $targetMenu = $menuData[$menuIndex]->menu ?? [];
      @endphp

      @foreach ($targetMenu as $menu)
        @php
          // 1. Addon Check
          if(isset($menu->addon) && !$addonService->isAddonEnabled($menu->addon)) continue;

          // 2. Standard Addon Check
          if(isset($menu->standardAddon) && !in_array($menu->standardAddon, $settings->available_modules ?? [])) continue;

          // 3. Role & Permission Check (Refined)
          $user = auth()->user();
          $isAdmin = $user->hasRole(['Admin', 'admin', 'super_admin', 'ADMIN', 'HR', 'hr']);
          
          if(isset($menu->roles)) {
              $userRoles = array_map('strtolower', $user->roles->pluck('name')->toArray());
              $requiredRoles = array_map('strtolower', (array) $menu->roles);
              if (empty(array_intersect($userRoles, $requiredRoles))) continue;
          }

          if(isset($menu->permission) && !$isAdmin) {
              if (!$user->can($menu->permission)) continue;
          }
        @endphp

        @if (!isset($menu->menuHeader))
          @php
            $activeClass = '';
            $currentRouteName = Route::currentRouteName();
            if ($currentRouteName === ($menu->slug ?? '')) {
              $activeClass = 'active';
            } elseif (isset($menu->submenu)) {
              if (gettype($menu->slug) === 'array') {
                foreach($menu->slug as $slug) {
                  if (str_contains($currentRouteName, $slug) && strpos($currentRouteName, $slug) === 0) {
                    $activeClass = 'active open';
                  }
                }
              } else {
                if (str_contains($currentRouteName, ($menu->slug ?? '')) && strpos($currentRouteName, ($menu->slug ?? '')) === 0) {
                  $activeClass = 'active open';
                }
              }
            }
          @endphp

          <li class="hitech-menu-item {{$activeClass}}">
            <a href="{{ $isLocked ? 'javascript:void(0);' : (isset($menu->url) ? url($menu->url) : 'javascript:void(0);') }}"
               class="hitech-menu-link {{ isset($menu->submenu) ? 'has-submenu' : '' }} {{ $isLocked ? 'hitech-menu-locked' : '' }}"
               @if (isset($menu->target) && !empty($menu->target)) target="_blank" @endif>
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
                       if(isset($submenu->standardAddon) && !in_array($submenu->standardAddon, $settings->available_modules ?? [])) continue;
                       
                       if(isset($submenu->roles)) {
                           $userRoles = array_map('strtolower', $user->roles->pluck('name')->toArray());
                           $requiredRoles = array_map('strtolower', (array) $submenu->roles);
                           if (empty(array_intersect($userRoles, $requiredRoles))) continue;
                       }

                       if(isset($submenu->permission) && !$isAdmin) {
                         if (!$user->can($submenu->permission)) continue;
                     }
                    @endphp
                    <li class="hitech-submenu-item {{ Route::currentRouteName() === $submenu->slug ? 'active' : '' }}">
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
            <img src="{{ auth()->user()->getProfilePicture() }}" alt="Profile">
          @else
            <div class="avatar-initials bg-label-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; font-weight: 600;">
              {{ auth()->user() ? auth()->user()->getInitials() : 'US' }}
            </div>
          @endif
        </div>
        <div class="hitech-user-meta">
          <span class="hitech-user-name">{{ auth()->user() ? auth()->user()->name : 'User' }}</span>
          <a href="{{ route('employee.myProfile') }}" class="hitech-user-role-link">
            <span class="hitech-user-role text-muted small">{{ auth()->user() ? (auth()->user()->roles->first()?->display_name ?: auth()->user()->roles->first()?->name ?: 'No Role') : 'Employee' }}</span>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
  
  // 1. Handle Submenu Accordion Toggles
  const menuLinks = document.querySelectorAll('.hitech-menu-link.has-submenu');
  
  menuLinks.forEach(link => {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      
      const parentItem = this.parentElement;
      const isOpen = parentItem.classList.contains('open');
      
      // Close other open menus at the same level (accordion behavior)
      const siblings = parentItem.parentElement.querySelectorAll('.hitech-menu-item.open');
      siblings.forEach(sibling => {
        if (sibling !== parentItem) {
          sibling.classList.remove('open');
        }
      });
      
      if (isOpen) {
        parentItem.classList.remove('open');
      } else {
        parentItem.classList.add('open');
      }
    });
  });

  // 2. Handle Mobile Sidebar Toggle
  const layoutMenuToggles = document.querySelectorAll('.layout-menu-toggle');
  const htmlEl = document.documentElement;
  
  layoutMenuToggles.forEach(toggle => {
    toggle.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      if (htmlEl.classList.contains('layout-menu-expanded')) {
        htmlEl.classList.remove('layout-menu-expanded');
      } else {
        htmlEl.classList.add('layout-menu-expanded');
      }
    });
  });

  const layoutOverlay = document.querySelector('.layout-overlay');
  if (layoutOverlay) {
    layoutOverlay.addEventListener('click', function(e) {
      if (htmlEl.classList.contains('layout-menu-expanded')) {
        htmlEl.classList.remove('layout-menu-expanded');
      }
    });
  }
});
</script>
