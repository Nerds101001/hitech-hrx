@php
  use Illuminate\Support\Facades\Route;
  $configData = Helper::appClasses();
@endphp

<!-- Quick Actions -->
<li class="nav-item dropdown dropdown-quick-create me-3 me-xl-1">
  <a class="nav-link dropdown-toggle hide-arrow hitech-dropdown-trigger" href="javascript:void(0);" data-bs-toggle="dropdown">
    <i class="bx bx-plus-circle"></i>
  </a>
  <ul class="dropdown-menu dropdown-menu-end hitech-dropdown-menu animate__animated animate__fadeIn">
    <li>
      <h6 class="dropdown-header">Quick Creation</h6>
    </li>
    @foreach ($menuData[2]->menu as $menu)
      <li>
        <a href="{{url($menu->url)}}" class="dropdown-item d-flex align-items-center">
          <i class='{{$menu->icon}} me-2 fs-5'></i>
          <span>@lang($menu->name)</span>
        </a>
      </li>
    @endforeach
  </ul>
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
