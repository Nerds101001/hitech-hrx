<!DOCTYPE html>
@php
  $menuFixed = ($configData['layout'] === 'vertical') ? ($menuFixed ?? '') : (($configData['layout'] === 'front') ? '' : $configData['headerType']);
  $navbarType = ($configData['layout'] === 'vertical') ? ($configData['navbarType'] ?? '') : (($configData['layout'] === 'front') ? 'layout-navbar-fixed': '');
  $isFront = ($isFront ?? '') == true ? 'Front' : '';
  $contentLayout = (isset($container) ? (($container === 'container-xxl') ? "layout-compact" : "layout-wide") : "");
@endphp

<html lang="{{ session()->get('locale') ?? app()->getLocale() }}"
      class="{{ $configData['style'] }}-style {{($contentLayout ?? '')}} {{ ($navbarType ?? '') }} {{ ($menuFixed ?? '') }} {{ $menuCollapsed ?? '' }} {{ $menuFlipped ?? '' }} {{ $menuOffcanvas ?? '' }} {{ $footerFixed ?? '' }} {{ $customizerHidden ?? '' }}"
      dir="{{ $configData['textDirection'] }}" data-theme="{{ $configData['theme'] }}"
      data-assets-path="{{ asset('/assets') . '/' }}" data-base-url="{{url('/')}}" data-framework="laravel"
      data-template="{{ $configData['layout'] . '-menu-' . $configData['themeOpt'] . '-' . $configData['styleOpt'] }}"
      data-style="{{$configData['styleOptVal']}}">

<head>
  <meta charset="utf-8" />
  <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>@yield('title') |
    {{ config('variables.templateName') ? config('variables.templateName') : 'TemplateName' }} -
    {{ config('variables.templateSuffix') ? config('variables.templateSuffix') : 'TemplateSuffix' }}
  </title>
  <meta name="description"
        content="{{ config('variables.templateDescription') ? config('variables.templateDescription') : '' }}" />
  <meta name="keywords"
        content="{{ config('variables.templateKeyword') ? config('variables.templateKeyword') : '' }}">
  <!-- laravel CRUD token -->
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <!-- Canonical SEO -->
  <link rel="canonical" href="{{ config('variables.productPage') ? config('variables.productPage') : '' }}">
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="{{ asset('assets/img/Fav.png') }}" />

  @if(config('custom.custom.isFirebaseEnabled'))

    <!-- Firebase SDKs End -->
  @endif

  <!-- Include Styles -->
  <!-- $isFront is used to append the front layout styles only on the front layout otherwise the variable will be blank -->
  @include('layouts/sections/styles' . $isFront)
  @yield('vendor-style')
  @yield('page-style')

  <style>
    .hitech-menu-locked {
        opacity: 0.6;
        cursor: not-allowed !important;
        pointer-events: none;
    }
    .menu-lock-icon {
        font-size: 0.9rem;
        color: #ff9f43;
    }
    .hitech-note-card {
        background: rgba(255, 159, 67, 0.1);
        border: 1px solid rgba(255, 159, 67, 0.2);
        border-radius: 12px;
        color: #854d0e;
    }
  </style>

  <!-- Include Scripts for customizer, helper, analytics, config -->
  <!-- $isFront is used to append the front layout scriptsIncludes only on the front layout otherwise the variable will be blank -->
  @include('layouts/sections/scriptsIncludes' . $isFront)

</head>

<body>

<!-- Layout Content -->
@yield('layoutContent')
<!--/ Layout Content -->


<!-- Common Modal -->
<div class="modal fade" id="commonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content modal-content-hitech">
            <div class="modal-header modal-header-hitech">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-header me-3"><i class="bx bx-info-circle fs-3"></i></div>
                    <h5 class="modal-title modal-title-hitech"></h5>
                </div>
                <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close"><i class="bx bx-x"></i></button>
            </div>
            <div class="modal-body modal-body-hitech">
                <!-- Content will be loaded here via AJAX -->
            </div>
        </div>
    </div>
</div>
<!-- / Common Modal -->

<!-- Include Scripts -->
<!-- $isFront is used to append the front layout scripts only on the front layout otherwise the variable will be blank -->
@include('layouts/sections/scripts' . $isFront)

</body>

</html>
