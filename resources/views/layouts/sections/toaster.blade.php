<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.css">
<script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>

<style>
    /* GLOBAL HITECH SWEETALERT2 STYLES */
    .swal2-popup.hitech-swal {
        border-radius: 20px !important;
        padding: 0 !important;
        overflow: hidden !important;
        border: none !important;
        font-family: 'Plus Jakarta Sans', sans-serif !important;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
    }
    .swal2-title.hitech-swal-title {
        background: linear-gradient(135deg, #003d3d 0%, #005a5a 100%) !important;
        color: #fff !important;
        margin: 0 !important;
        padding: 1.25rem 2rem !important;
        font-size: 1.15rem !important;
        font-weight: 800 !important;
        text-align: left !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
    }
    .swal2-title.hitech-swal-title::before {
        content: '\eb92'; /* bx-error-alt */
        font-family: 'boxicons' !important;
        margin-right: 12px;
        background: rgba(255,255,255,0.15);
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        font-size: 1.25rem;
    }
    .swal2-html-container.hitech-swal-html {
        padding: 2.5rem 2rem !important;
        margin: 0 !important;
        text-align: left !important;
        color: #334155 !important;
        font-size: 0.95rem !important;
        font-weight: 600 !important;
        line-height: 1.6 !important;
    }
    .swal2-actions.hitech-swal-actions {
        padding: 0 2rem 2rem 2rem !important;
        margin: 0 !important;
        justify-content: flex-end !important;
        width: 100% !important;
        gap: 12px !important;
    }
    .swal2-confirm.hitech-swal-confirm {
        background: #006D77 !important;
        color: #fff !important;
        border-radius: 12px !important;
        font-weight: 700 !important;
        padding: 12px 28px !important;
        font-size: 0.85rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
        transition: all 0.2s ease !important;
        box-shadow: 0 4px 12px rgba(0, 109, 119, 0.2) !important;
        border: none !important;
        margin: 0 !important;
    }
    .swal2-confirm.hitech-swal-confirm:hover {
        background: #005a63 !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 6px 20px rgba(0, 109, 119, 0.3) !important;
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

<script>
  if (typeof window.notyf === 'undefined') {
    window.notyf = new Notyf();
  }

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
