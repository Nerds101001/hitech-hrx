{{-- CDNs removed for stability, relying on local vendor assets where possible --}}

<style>
    /* GLOBAL HITECH SWEETALERT2 STYLES */
    .swal2-popup.hitech-swal {
        border-radius: 24px !important;
        padding: 0 !important;
        overflow: hidden !important;
        border: 1px solid rgba(0,0,0,0.05) !important;
        font-family: 'Plus Jakarta Sans', sans-serif !important;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
    }
    .swal2-title.hitech-swal-title {
        background: linear-gradient(135deg, #003d3d 0%, #005a5a 100%) !important;
        color: #fff !important;
        margin: 0 !important;
        padding: 1.5rem 2rem !important;
        font-size: 1.25rem !important;
        font-weight: 800 !important;
        text-align: left !important;
        display: flex !important;
        align-items: center !important;
        gap: 1rem !important;
        border-bottom: 1px solid rgba(255,255,255,0.1) !important;
    }
    .swal2-title.hitech-swal-title::before {
        content: '\eb92'; /* bx-error-alt */
        font-family: 'boxicons' !important;
        background: rgba(255,255,255,0.15);
        min-width: 40px;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    .swal2-html-container.hitech-swal-html {
        padding: 2rem 2rem !important;
        margin: 0 !important;
        text-align: left !important;
        color: #334155 !important;
        font-size: 0.95rem !important;
        font-weight: 500 !important;
        line-height: 1.6 !important;
    }
    .swal2-actions.hitech-swal-actions {
        padding: 0 2rem 2rem 2rem !important;
        margin: 0 !important;
        justify-content: flex-end !important;
        gap: 10px !important;
    }
    .swal2-confirm.hitech-swal-confirm {
        background: var(--deep-teal) !important;
        color: #fff !important;
        border-radius: 12px !important;
        padding: 0.75rem 1.75rem !important;
        font-weight: 700 !important;
        font-size: 0.85rem !important;
        border: none !important;
        box-shadow: 0 4px 12px rgba(0, 77, 84, 0.15) !important;
        transition: all 0.3s ease !important;
    }
    .swal2-confirm.hitech-swal-confirm:hover {
        background: var(--primary-teal) !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 8px 16px rgba(0, 109, 119, 0.2) !important;
    }
    .swal2-cancel.hitech-swal-cancel {
        background: #F1F5F9 !important;
        color: #475569 !important;
        border-radius: 12px !important;
        font-weight: 700 !important;
        padding: 12px 28px !important;
        font-size: 0.85rem !important;
        text-transform: uppercase !important;
        border: 1px solid #E2E8F0 !important;
        transition: all 0.2s ease !important;
        margin: 0 !important;
    }
    .swal2-icon {
        display: none !important; /* Use our custom header styling instead of standard icon */
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
      title: 'Success!',
      text: message,
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

  window.showInfoSwal = function(message) {
    return Swal.fire({
      title: 'Information',
      html: `<div style="font-family: 'Plus Jakarta Sans', sans-serif;">${message}</div>`,
      icon: 'info',
      customClass: {
        popup: 'hitech-swal animate__animated animate__fadeInDown',
        title: 'hitech-swal-title hitech-swal-info',
        htmlContainer: 'hitech-swal-html',
        actions: 'hitech-swal-actions',
        confirmButton: 'hitech-swal-confirm'
      },
      buttonsStyling: false,
      confirmButtonText: 'Got it'
    });
  };

  window.showWarningSwal = function(message) {
    return Swal.fire({
      title: 'Warning!',
      html: `<div style="font-family: 'Plus Jakarta Sans', sans-serif;">${message}</div>`,
      icon: 'warning',
      customClass: {
        popup: 'hitech-swal animate__animated animate__fadeInDown',
        title: 'hitech-swal-title hitech-swal-warning',
        htmlContainer: 'hitech-swal-html',
        actions: 'hitech-swal-actions',
        confirmButton: 'hitech-swal-confirm'
      },
      buttonsStyling: false,
      confirmButtonText: 'OK'
    });
  };

  window.showErrorSwal = function(message) {
    Swal.fire({
      title: 'Error!',
      text: message,
      customClass: { 
        popup: 'hitech-swal animate__animated animate__shakeX', 
        title: 'hitech-swal-title', 
        htmlContainer: 'hitech-swal-html',
        actions: 'hitech-swal-actions',
        confirmButton: 'hitech-swal-confirm' 
      },
      buttonsStyling: false
    });
  };

  window.showErrorSwalHtml = function(message) {
    Swal.fire({
      title: 'Error!',
      html: message,
      customClass: { 
        popup: 'hitech-swal animate__animated animate__shakeX', 
        title: 'hitech-swal-title', 
        htmlContainer: 'hitech-swal-html',
        actions: 'hitech-swal-actions',
        confirmButton: 'hitech-swal-confirm' 
      },
      buttonsStyling: false
    });
  };

</script>
