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

@auth
{{-- Versioned by file mtime -- the static-asset Cache-Control on this file
     is a long 7 days with no query string, so a plain asset() URL could
     leave an already-cached browser running old code (including missing an
     entire feature added later) indefinitely. This changes the URL itself
     whenever the file changes, which busts the cache unconditionally. --}}
<script src="{{ asset('assets/js/webpush-subscribe.js') }}?v={{ @filemtime(public_path('assets/js/webpush-subscribe.js')) ?: '1' }}"></script>
@if(config('webpush.public_key'))
<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (window.HrxWebPush) {
      window.HrxWebPush.autoInit('{{ config('webpush.public_key') }}', '{{ csrf_token() }}');
    }
  });
</script>
@endif
@endauth

<!-- BEGIN: Page JS-->
@yield('page-script')
{{-- Pages that use @push('scripts') instead of @section('page-script').
     No layout rendered this stack, so their JS was silently dropped —
     the lifecycle chart, promotions page and my-assets page all had
     dead scripts because of it. --}}
@stack('scripts')
<!-- END: Page JS-->

@include('layouts.sections.toaster')

<script>
  // Global DataTables Error Handler to suppress ugly browser alerts on Session Timeout (401/419)
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof $.fn !== 'undefined' && typeof $.fn.dataTable !== 'undefined') {
        $.fn.dataTable.ext.errMode = 'none';
        $(document).on('error.dt', function (e, settings, techNote, message) {
            // This used to fire "Secure Session Expired" for literally ANY AJAX
            // failure -- a 404 from a routing bug, a genuine 500, a network
            // blip, all got the same false "your session timed out" message,
            // which sent people chasing a session/CSRF problem that didn't
            // exist (confirmed case: my-assets' getListAjax 404ing into a
            // catch-all route). Only an actual 401/419 IS a session/CSRF
            // expiry; everything else gets its own honest message and a
            // retry that reloads just the table, not the whole page.
            var status = settings && settings.jqXHR ? settings.jqXHR.status : null;
            var isSessionExpiry = status === 401 || status === 419;

            if (typeof Swal === 'undefined') {
                console.warn('DataTables Error:', message);
                return;
            }

            if (isSessionExpiry) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Secure Session Expired',
                    text: 'Your connection has timed out. Please reload to generate a new security token.',
                    confirmButtonText: '<i class="bx bx-refresh"></i> Reload Session',
                    customClass: { confirmButton: 'btn btn-primary shadow-sm' },
                    buttonsStyling: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.reload();
                    }
                });
            } else {
                console.warn('DataTables Error:', status, message);
                Swal.fire({
                    icon: 'error',
                    title: 'Could not load this data',
                    text: 'Something went wrong loading this table. You can try again, or contact support if it keeps happening.',
                    confirmButtonText: '<i class="bx bx-refresh"></i> Try again',
                    customClass: { confirmButton: 'btn btn-primary shadow-sm' },
                    buttonsStyling: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        try {
                            new $.fn.dataTable.Api(settings).ajax.reload(null, false);
                        } catch (err) {
                            window.location.reload();
                        }
                    }
                });
            }
        });
    }
  });
</script>
