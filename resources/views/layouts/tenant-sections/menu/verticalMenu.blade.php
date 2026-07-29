@php
  use App\Services\AddonService\IAddonService;
  use Illuminate\Support\Facades\Route;
  $configData = Helper::appClasses();
  $addonService = app(IAddonService::class);
  $isLocked = auth()->check() && auth()->user()->status === \App\Enums\UserAccountStatus::ONBOARDING_SUBMITTED;
@endphp

{{-- 1. SIDEBAR CONTAINER --}}
{{-- Collapsed ("icon rail") state. This custom sidebar had no styling for the
     theme's layout-menu-collapsed class at all, so minimising it left labels
     clipped mid-word and the profile card overflowing. --}}
<style>
  .hitech-logo-mark { display: none; height: 34px; width: 34px; object-fit: contain; }

  html.layout-menu-collapsed:not(.layout-menu-hover) .hitech-sidebar {
    width: 78px !important;
  }
  /* Logo: full wordmark out, square mark in, centred on the rail */
  html.layout-menu-collapsed:not(.layout-menu-hover) .hitech-sidebar .hitech-logo-full { display: none; }
  html.layout-menu-collapsed:not(.layout-menu-hover) .hitech-sidebar .hitech-logo-mark { display: block; }
  html.layout-menu-collapsed:not(.layout-menu-hover) .hitech-sidebar .hitech-logo-area {
    justify-content: center !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
  }

  /* Icons only — no labels, no submenus, no section headings */
  html.layout-menu-collapsed:not(.layout-menu-hover) .hitech-sidebar .hitech-menu-text,
  html.layout-menu-collapsed:not(.layout-menu-hover) .hitech-sidebar .hitech-submenu,
  html.layout-menu-collapsed:not(.layout-menu-hover) .hitech-sidebar .menu-lock-icon,
  html.layout-menu-collapsed:not(.layout-menu-hover) .hitech-sidebar .hitech-menu-header,
  html.layout-menu-collapsed:not(.layout-menu-hover) .hitech-sidebar .menu-header {
    display: none !important;
  }
  html.layout-menu-collapsed:not(.layout-menu-hover) .hitech-sidebar .hitech-menu-link {
    justify-content: center;
    padding-left: 0 !important;
    padding-right: 0 !important;
  }
  html.layout-menu-collapsed:not(.layout-menu-hover) .hitech-sidebar .hitech-menu-icon {
    margin: 0 !important;
    font-size: 1.35rem;
  }
  html.layout-menu-collapsed:not(.layout-menu-hover) .hitech-sidebar .hitech-menu-link::after {
    display: none !important;   /* submenu caret */
  }

  /* Profile card: just the round avatar */
  html.layout-menu-collapsed:not(.layout-menu-hover) .hitech-sidebar .hitech-user-meta,
  html.layout-menu-collapsed:not(.layout-menu-hover) .hitech-sidebar .hitech-profile-actions {
    display: none !important;
  }
  html.layout-menu-collapsed:not(.layout-menu-hover) .hitech-sidebar .hitech-profile-card {
    padding-left: 0 !important;
    padding-right: 0 !important;
  }
  html.layout-menu-collapsed:not(.layout-menu-hover) .hitech-sidebar .hitech-profile-info {
    justify-content: center !important;
  }
  html.layout-menu-collapsed:not(.layout-menu-hover) .hitech-sidebar .hitech-avatar,
  html.layout-menu-collapsed:not(.layout-menu-hover) .hitech-sidebar .hitech-avatar img {
    width: 38px !important;
    height: 38px !important;
    border-radius: 50% !important;
    object-fit: cover;
    margin: 0 !important;
  }

  /* Hovering the rail restores the full menu (theme adds layout-menu-hover) */
  html.layout-menu-collapsed.layout-menu-hover .hitech-sidebar { width: 260px !important; }
</style>

<aside id="layout-menu" class="hitech-sidebar layout-menu menu-vertical menu" style="background-color: #ffffff !important;">

  {{-- 1. LOGO HUB --}}
  <div class="hitech-logo-area d-flex align-items-center justify-content-start px-4" style="height: 70px; background: #ffffff !important; border-bottom: 1px solid rgba(0,0,0,0.05);">
    <a href="{{url('/')}}" class="hitech-logo-link d-flex align-items-center">
      <img src="{{ asset('assets/img/logo.png') }}" alt="HRX Logo" class="hitech-logo-full"
           style="max-height: 45px; max-width: 160px; width: auto; object-fit: contain;">
      {{-- Shown only when the sidebar is collapsed to the icon rail. --}}
      <img src="{{ asset('assets/img/logo-mark.png') }}" alt="HRX" class="hitech-logo-mark">
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

        // Headers are filtered independently of their items, so a role with no
        // items in a section still saw its header — e.g. an employee got an
        // empty "Administration" heading. Pre-compute, per header, whether any
        // item beneath it survives the same gates the render loop applies, and
        // drop the ones that would head an empty group.
        $menuUser = auth()->user();
        $itemVisible = function ($m) use ($menuUser, $addonService, $settings) {
            if (isset($m->addon) && !$addonService->isAddonEnabled($m->addon)) return false;
            if (isset($m->standardAddon)
                && !in_array($m->standardAddon, $settings->available_modules ?? [])) return false;

            $isAdminUser = $menuUser
                ? $menuUser->hasRole(['Admin', 'admin', 'super_admin', 'ADMIN', 'HR', 'hr'])
                : false;

            if (isset($m->roles)) {
                if (!$menuUser) return false;
                $ur = array_map('strtolower', $menuUser->roles->pluck('name')->toArray());
                $rr = array_map('strtolower', (array) $m->roles);
                if (empty(array_intersect($ur, $rr))) return false;
            }
            if (isset($m->permission) && !$isAdminUser) {
                if (!$menuUser || !$menuUser->can($m->permission)) return false;
            }
            if (isset($m->department) && !$isAdminUser) {
                if (!$menuUser || !$menuUser->department
                    || stripos($menuUser->department->name, $m->department) === false) return false;
            }
            return true;
        };

        $liveHeaders = [];
        $pendingHeader = null;
        foreach ($targetMenu as $entry) {
            if (isset($entry->menuHeader)) {
                $pendingHeader = $entry->menuHeader;
                continue;
            }
            if ($pendingHeader !== null && $itemVisible($entry)) {
                $liveHeaders[$pendingHeader] = true;
                $pendingHeader = null;   // this header is justified; stop testing it
            }
        }
      @endphp

      @foreach ($targetMenu as $menu)
        @php
          // 1. Addon Check
          if(isset($menu->addon) && !$addonService->isAddonEnabled($menu->addon)) continue;

          // 2. Standard Addon Check
          if(isset($menu->standardAddon) && !in_array($menu->standardAddon, $settings->available_modules ?? [])) continue;

          // 3. Role & Permission Check (Refined)
          $user = auth()->user();
          $isAdmin = $user ? $user->hasRole(['Admin', 'admin', 'super_admin', 'ADMIN', 'HR', 'hr']) : false;
          
          if(isset($menu->roles)) {
              if (!$user) continue;
              $userRoles = array_map('strtolower', $user->roles->pluck('name')->toArray());
              $requiredRoles = array_map('strtolower', (array) $menu->roles);
              if (empty(array_intersect($userRoles, $requiredRoles))) continue;
          }

          if(isset($menu->permission) && !$isAdmin) {
              if (!$user || !$user->can($menu->permission)) continue;
          }

          if(isset($menu->department)) {
              if (!$user || !$user->department || stripos($user->department->name, $menu->department) === false) {
                  // Only skip if they aren't admin/hr and don't match the department
                  if (!$isAdmin) continue;
              }
          }
        @endphp

        {{-- Section headings (MAIN, PEOPLE & HR, …) are deliberately NOT drawn.
             They are kept in the menu JSON because the grouping still orders the
             items sensibly, but the labels themselves added vertical noise to an
             already long sidebar. Restore by re-enabling the block below. --}}
        @if (false && isset($menu->menuHeader) && !empty($liveHeaders[$menu->menuHeader]))
          <li class="hitech-menu-header">
            <span class="hitech-menu-header-text">{{ __($menu->menuHeader) }}</span>
          </li>
        @endif

        @if (!isset($menu->menuHeader))
          @php
            // A parent was only compared against its OWN slug list, so opening a
            // page whose route is not in that list (Sales Report, Sales Pipeline,
            // Kingo Bingo...) left the sidebar with nothing highlighted and the
            // group collapsed. Match the submenu slugs too, and treat
            // "<slug>.<something>" as belonging to <slug>.
            $currentRouteName = Route::currentRouteName() ?? '';
            $menuMatches = function ($slug) use ($currentRouteName) {
              foreach ((array) $slug as $s) {
                if (!is_string($s) || $s === '') continue;
                if ($currentRouteName === $s || str_starts_with($currentRouteName, $s . '.')) return true;
              }
              return false;
            };
            $activeClass = '';
            if ($menuMatches($menu->slug ?? '')) {
              $activeClass = isset($menu->submenu) ? 'active open' : 'active';
            }
            if (isset($menu->submenu)) {
              foreach ($menu->submenu as $sm) {
                if ($menuMatches($sm->slug ?? '')) { $activeClass = 'active open'; break; }
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
                           if (!$user) continue;
                           $userRoles = array_map('strtolower', $user->roles->pluck('name')->toArray());
                           $requiredRoles = array_map('strtolower', (array) $submenu->roles);
                           if (empty(array_intersect($userRoles, $requiredRoles))) continue;
                       }

                       if(isset($submenu->permission) && !$isAdmin) {
                         if (!$user || !$user->can($submenu->permission)) continue;
                     }
                    @endphp
                    <li class="hitech-submenu-item {{ $menuMatches($submenu->slug ?? '') ? 'active' : '' }}">
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
          @php
            $roleLabels = ['admin'=>'Administrator','hr'=>'HR Manager','manager'=>'Manager','employee'=>'Employee','office_employee'=>'Employee','accounts'=>'Accounts'];
            $rawRole = auth()->user()?->roles->first()?->name ?? 'employee';
            $roleLabel = $roleLabels[strtolower($rawRole)] ?? ucwords(str_replace('_',' ',$rawRole));
          @endphp
          <span class="hitech-user-role text-muted small">{{ $roleLabel }}</span>
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
