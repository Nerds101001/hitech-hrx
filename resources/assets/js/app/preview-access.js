/**
 * Preview Access modal — shared by roles/index.blade.php and
 * roles/special-permissions.blade.php (both include the modal partial).
 * Delegated on document, so it works no matter which page loaded it.
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  function escapeHtml(str) {
    return $('<div>').text(str == null ? '' : str).html();
  }

  function renderPreviewMenu(payload) {
    $('#previewEmpty, #previewLoading').addClass('d-none');
    $('#previewResults').removeClass('d-none');
    $('#previewLabel').text(payload.label);

    var visibleTop = payload.menu.filter(function (m) { return m.visible; }).length;
    $('#previewStat').text(visibleTop + ' / ' + payload.menu.length);

    var html = '';
    payload.menu.forEach(function (item) {
      html += '<div class="pmt-item ' + (item.visible ? 'pmt-visible' : 'pmt-hidden') + '">';
      html += '  <div class="pmt-row">';
      html += '    <div class="pmt-icon"><i class="' + (item.icon || 'bx bx-square') + '"></i></div>';
      html += '    <div class="pmt-name">' + escapeHtml(item.name) + '</div>';
      html += '    <span class="pmt-badge ' + (item.visible ? 'pmt-badge-on' : 'pmt-badge-off') + '">' + (item.visible ? 'VISIBLE' : 'HIDDEN') + '</span>';
      html += '  </div>';
      if (!item.visible && item.reason) {
        html += '  <div class="pmt-reason">' + escapeHtml(item.reason) + '</div>';
      }
      if (item.submenu && item.submenu.length) {
        html += '  <div class="pmt-sub">';
        item.submenu.forEach(function (sub) {
          var subColor = sub.visible ? 'var(--hitech-primary)' : '#ced4da';
          var subStyle = sub.visible ? '' : 'color:#adb5bd;text-decoration:line-through;text-decoration-color:#e9ecef;';
          html += '    <div class="pmt-row"' + (sub.reason ? ' title="' + escapeHtml(sub.reason) + '"' : '') + '>';
          html += '      <i class="bx bx-chevron-right" style="color:' + subColor + ';font-size:14px;"></i>';
          html += '      <div class="pmt-name" style="' + subStyle + '">' + escapeHtml(sub.name) + '</div>';
          html += '    </div>';
        });
        html += '  </div>';
      }
      html += '</div>';
    });
    $('#previewMenuTree').html(html);
  }

  $(document).on('click', '#runPreview', function () {
    var val = $('#previewTarget').val();
    if (!val) return;

    var payload = val.indexOf('role:') === 0
      ? { role: val.substring(5) }
      : { user_id: val.substring(5) };

    $('#previewEmpty, #previewResults').addClass('d-none');
    $('#previewLoading').removeClass('d-none');
    $('#runPreview').prop('disabled', true);

    $.ajax({
      url: `${baseUrl}roles/previewAccessAjax`,
      type: 'POST',
      data: payload,
      success: function (res) {
        $('#runPreview').prop('disabled', false);
        if (!res || res.status !== 'success') {
          $('#previewLoading').addClass('d-none');
          $('#previewEmpty').removeClass('d-none');
          Swal.fire({ icon: 'error', title: 'Error', text: (res && res.data) || 'Could not load the preview.' });
          return;
        }
        renderPreviewMenu(res.data);
      },
      error: function (err) {
        $('#runPreview').prop('disabled', false);
        $('#previewLoading').addClass('d-none');
        $('#previewEmpty').removeClass('d-none');
        Swal.fire({ icon: 'error', title: 'Error', text: (err.responseJSON && err.responseJSON.data) || 'Could not load the preview.' });
      }
    });
  });

  // Reset to the picker each time the modal is reopened, rather than
  // showing a stale preview from whoever was looked up last time.
  $(document).on('hidden.bs.modal', '#previewAccessModal', function () {
    $('#previewTarget').val('');
    $('#previewResults').addClass('d-none');
    $('#previewLoading').addClass('d-none');
    $('#previewEmpty').removeClass('d-none');
  });
});
