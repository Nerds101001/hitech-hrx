<!-- BEGIN: Vendor JS-->

@vite([
  'resources/assets/vendor/libs/jquery/jquery.js',
  'resources/assets/vendor/libs/popper/popper.js',
  'resources/assets/vendor/js/bootstrap.js',
  'resources/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js',
  'resources/assets/vendor/libs/hammer/hammer.js',
  'resources/assets/vendor/libs/typeahead-js/typeahead.js',
  'resources/assets/vendor/js/menu.js',
  'resources/js/main-helper.js'
])

@yield('vendor-script')
<!-- END: Page Vendor JS-->
<!-- BEGIN: Theme JS-->
@vite(['resources/assets/js/main.js'])

<!-- END: Theme JS-->
{{--<!-- Pricing Modal JS-->
@stack('pricing-script')
<!-- END: Pricing Modal JS-->--}}


{{--<!-- BEGIN: Firebase JS-->
<script type="module" src="{{asset('assets/js/firebase-messaging-sw.js')}}"></script>
<!-- END: Firebase JS-->--}}

<!-- BEGIN: Page JS-->
@yield('page-script')
<!-- END: Page JS-->

@include('layouts.sections.toaster')

