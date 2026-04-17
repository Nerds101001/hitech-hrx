{{-- CDNs removed for stability, relying on local vendor assets where possible --}}

<style>
    /* GLOBAL HITECH SWEETALERT2 STYLES - PREMIUM OVERHAUL */
    .swal2-popup.hitech-swal {
        border-radius: 20px !important;
        padding: 0 !important;
        overflow: hidden !important;
        border: 1px solid rgba(0, 77, 84, 0.1) !important;
        background: #fff !important;
        font-family: 'Plus Jakarta Sans', sans-serif !important;
        box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.15) !important;
    }
    .swal2-title.hitech-swal-title {
        background: #f8fafc !important;
        color: #0f172a !important;
        margin: 0 !important;
        padding: 1.5rem 2rem 0.5rem !important;
        font-size: 1.5rem !important;
        font-weight: 800 !important;
        text-align: left !important;
        display: flex !important;
        align-items: center !important;
        gap: 0.75rem !important;
    }
    .swal2-title.hitech-swal-title i {
        color: #008080;
    }
    .swal2-html-container.hitech-swal-html {
        padding: 0.5rem 2rem 1.5rem !important;
        margin: 0 !important;
        text-align: left !important;
        color: #475569 !important;
        font-size: 0.95rem !important;
        font-weight: 500 !important;
        line-height: 1.6 !important;
    }
    
    /* Footer Protocol Styling */
    .hitech-swal-footer {
        padding: 1rem 2rem !important;
        background: #f8fafc !important;
        border-top: 1px solid #e2e8f0 !important;
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        font-size: 10px !important;
        font-weight: 800 !important;
        color: #94a3b8 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
    }

    .swal2-actions.hitech-swal-actions {
        padding: 1rem 2rem 2rem !important;
        margin: 0 !important;
        justify-content: flex-end !important;
        background: #fff !important;
    }
    .swal2-confirm.hitech-swal-confirm {
        background: #004D54 !important;
        color: #fff !important;
        border-radius: 12px !important;
        padding: 0.8rem 2rem !important;
        font-weight: 700 !important;
        font-size: 0.85rem !important;
        border: none !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        box-shadow: 0 10px 15px -3px rgba(0, 77, 84, 0.2) !important;
    }
    .swal2-confirm.hitech-swal-confirm:hover {
        background: #006D77 !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 20px 25px -5px rgba(0, 109, 119, 0.3) !important;
    }
    .swal2-icon {
        display: none !important;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof Notyf !== 'undefined' && typeof window.notyf === 'undefined') {
      window.notyf = new Notyf({
        duration: 4000,
        position: { x: 'right', y: 'bottom' },
        types: [
          { type: 'success', background: '#006D77', icon: false },
          { type: 'error', background: '#dc2626', icon: false },
          { type: 'warning', background: '#f59e0b', icon: false }
        ]
      });
    }
  });

  window.notyf = window.notyf || {
    success: (m) => console.log('Toast:', m),
    error: (m) => console.error('Toast:', m),
    warning: (m) => console.warn('Toast:', m),
    info: (m) => console.info('Toast:', m)
  };

  // success message popup notification
  @if(session()->has('success'))
  window.notyf.success("{{ session()->get('success') }}");
  @endif

  // info message popup notification
  @if(session()->has('info'))
  window.notyf.info("{{ session()->get('info') }}");
  @endif

  // warning message popup notification
  @if(session()->has('warning'))
  window.notyf.warning("{{ session()->get('warning') }}");
  @endif

  // error message popup notification
  @if(session()->has('error'))
  window.notyf.error("{{ session()->get('error') }}");
  @endif

  @if ($errors->any())
  (function() {
    var errorMessages = `{!! implode('<br>', $errors->all()) !!}`;
    if (typeof window.showErrorSwalHtml === 'function') {
      window.showErrorSwalHtml(errorMessages);
    }
  })();
  @endif


  window.showSuccessToast = function(message) {
    window.notyf.success(message);
  };

  window.showErrorToast = function(message) {
    window.notyf.error(message);
  };

  window.showInfoToast = function(message) {
    window.notyf.info(message);
  };

  window.showWarningToast = function(message) {
    window.notyf.warning(message);
  };

  window.showSuccessSwal = function(message) {
    Swal.fire({
      title: '<i class="bx bx-check-circle"></i> Success!',
      text: message,
      html: `
        <div class="hitech-swal-html">${message}</div>
        <div class="hitech-swal-footer">
            <i class="bx bx-shield-quarter"></i>
            PROTOCOL: SYSTEM_EXECUTION_SUCCESS
        </div>
      `,
      customClass: { 
        popup: 'hitech-swal', 
        title: 'hitech-swal-title', 
        htmlContainer: 'hitech-swal-html',
        actions: 'hitech-swal-actions',
        confirmButton: 'hitech-swal-confirm' 
      },
      buttonsStyling: false
    });
  };

  window.showErrorSwal = function(message) {
    Swal.fire({
      title: '<i class="bx bx-error-alt"></i> Error!',
      html: `
        <div class="hitech-swal-html">${message}</div>
        <div class="hitech-swal-footer">
            <i class="bx bx-shield-quarter"></i>
            PROTOCOL: SYSTEM_EXECUTION_FAILURE
        </div>
      `,
      customClass: { 
        popup: 'hitech-swal animate__animated animate__shakeX', 
        title: 'hitech-swal-title', 
        htmlContainer: 'hitech-swal-html-wrapper', // dummy to avoid double padding
        actions: 'hitech-swal-actions',
        confirmButton: 'hitech-swal-confirm' 
      },
      buttonsStyling: false
    });
  };

  window.showErrorSwalHtml = function(message) {
      window.showErrorSwal(message);
  };

</script>
