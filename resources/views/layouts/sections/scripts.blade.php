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

<script>
  // Global DataTables Error Handler to suppress ugly browser alerts on Session Timeout (401/419)
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof $.fn !== 'undefined' && typeof $.fn.dataTable !== 'undefined') {
        $.fn.dataTable.ext.errMode = 'none';
        $(document).on('error.dt', function (e, settings, techNote, message) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Secure Session Expired',
                    text: 'Your connection has timed out. Please reload to generate a new security token.',
                    confirmButtonText: '<i class="bx bx-refresh"></i> Reload Session',
                    customClass: {
                        confirmButton: 'btn btn-primary shadow-sm'
                    },
                    buttonsStyling: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.reload();
                    }
                });
            } else {
                console.warn('DataTables Error:', message);
            }
        });
    }
  });
</script>
