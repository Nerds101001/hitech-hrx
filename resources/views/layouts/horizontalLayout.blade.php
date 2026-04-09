@isset($pageConfigs)
{!! Helper::updatePageConfig($pageConfigs) !!}
@endisset
@php
$configData = Helper::appClasses();
@endphp

@extends('layouts/commonMaster' )
@php

$menuHorizontal = true;
$navbarFull = true;

/* Display elements */
$isNavbar = ($isNavbar ?? true);
$isMenu = ($isMenu ?? true);
$isFlex = ($isFlex ?? false);
$isFooter = ($isFooter ?? true);
$customizerHidden = ($customizerHidden ?? '');

/* HTML Classes */
$menuFixed = (isset($configData['menuFixed']) ? $configData['menuFixed'] : '');
$navbarType = (isset($configData['navbarType']) ? $configData['navbarType'] : '');
$footerFixed = (isset($configData['footerFixed']) ? $configData['footerFixed'] : '');
$menuCollapsed = (isset($configData['menuCollapsed']) ? $configData['menuCollapsed'] : '');

/* Content classes */
$container = ($configData['contentLayout'] === 'compact') ? 'container-xxl' : 'container-fluid';
$containerNav = ($configData['contentLayout'] === 'compact') ? 'container-xxl' : 'container-fluid';

@endphp

@section('layoutContent')
  {{-- Horizontal layout redirected to Hitech vertical layout for design consistency --}}
  <div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">

      @if ($isMenu)
        @include('layouts/tenant-sections/menu/verticalMenu')
      @endif

      <div class="layout-page">

        @if ($isNavbar)
          @include('layouts/sections/navbar/navbar')
        @endif

        <div class="content-wrapper">
          @if ($isFlex)
            <div class="{{$container}} d-flex align-items-stretch flex-grow-1 p-0">
          @else
            <div class="{{$container}} flex-grow-1 container-p-y">
          @endif

            @yield('content')

          </div>

          @if ($isFooter)
            @include('layouts/sections/footer/footer')
          @endif
          <div class="content-backdrop fade"></div>
        </div>
      </div>
    </div>

    @if ($isMenu)
      <div class="layout-overlay layout-menu-toggle"></div>
    @endif
    <div class="drag-target"></div>
  </div>
@endsection
