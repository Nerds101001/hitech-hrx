@php
  use Illuminate\Support\Facades\Route;
  $configData = Helper::appClasses();
@endphp

  <!-- Quick Create DropDown -->
<li class="nav-item dropdown dropdown-quick-create me-2 me-xl-0">
  <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
    <i
      data-bs-toggle="tooltip"
      data-bs-placement="top"
      title="@lang('Quick Create')"
      class="bx bx-plus-circle text-heading"></i>
  </a>
  <div class="dropdown-menu dropdown-menu-end hitech-dropdown-menu animate__animated animate__fadeIn">
    <div class="px-2 py-1 mb-2 border-bottom">
        <h6 class="mb-0 fw-bold" style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Quick Actions</h6>
    </div>
    <div class="quick-create-grid">
      @foreach ($menuData[2]->menu as $menu)
        @php
            $isCustomer = str_contains(strtolower($menu->name), 'customer');
            $isClient = str_contains(strtolower($menu->name), 'client');
            $isDisabled = !$isCustomer; // Disable everything except Customer
            
            // If it's client, we specifically keep it disabled as per request
            // If it's neither customer nor client, we disable it for "just disabled others"
        @endphp
        
        <a href="{{ $isDisabled ? 'javascript:void(0);' : url($menu->url) }}" 
           class="dropdown-item {{ $isDisabled ? 'disabled' : '' }}"
           title="{{ $isClient ? 'Currently Disabled' : '' }}">
          @if(isset($menu->icon))
            <i class='{{ $menu->icon }}'></i>
          @else
            <i class='bx bx-plus'></i>
          @endif
          <span>@lang($menu->name)</span>
        </a>
      @endforeach
    </div>
  </div>
</li>
<!-- /Quick Create DropDown -->

{{--
<a href="javascript:void(0);" class="dropdown-item">
  <i class=' bx bx-group'></i>
  <span>@lang('User')</span>
</a>
<a href="javascript:void(0);" class="dropdown-item">
  <i class='bx bx-bell'></i>
  <span>@lang('Notification')</span>
</a>--}}
