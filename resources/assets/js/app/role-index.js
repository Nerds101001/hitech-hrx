/**
 * Roles & Permissions — master-detail shell.
 * Owns: the role list (search + AJAX-swap the detail panel), the Create
 * Role modal, delete, and every control inside the panel itself. Panel
 * content is replaced wholesale on each role switch, so every panel-internal
 * handler below is delegated (bound on document), never bound directly —
 * a direct binding would only ever fire for the first-rendered panel.
 */

'use strict';

document.addEventListener('DOMContentLoaded', function (e) {
  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
  });

  function initTooltips() {
    $('[data-bs-toggle="tooltip"]').tooltip();
  }
  initTooltips();

  // ── Role list: search + switch ──────────────────────────────────────
  var currentRoleId = $('.rp-role-row.rp-role-active').data('id') || null;

  $('#rpRoleSearch').on('keyup', function () {
    var q = $.trim($(this).val()).toLowerCase();
    $('.rp-role-row').each(function () {
      $(this).toggleClass('d-none', !(!q || ($(this).data('name') || '').toString().indexOf(q) !== -1));
    });
  });

  function loadRolePanel(roleId) {
    currentRoleId = roleId;
    $('.rp-role-row').removeClass('rp-role-active');
    $('.rp-role-row[data-id="' + roleId + '"]').addClass('rp-role-active');

    $.get(`${baseUrl}roles/${roleId}/panelAjax`, function (html) {
      $('#rpPanel').html(html);
      initTooltips();
      recomputeCounts();
      if (window.history && window.history.replaceState) {
        window.history.replaceState(null, '', `${baseUrl}roles?role=${roleId}`);
      }
    }).fail(function () {
      Swal.fire({ icon: 'error', title: 'Error', text: 'Could not load that role.' });
    });
  }

  $(document).on('click', '.rp-role-row', function () {
    var id = $(this).data('id');
    if (id !== currentRoleId) loadRolePanel(id);
  });

  // ── Panel: live permission counters ─────────────────────────────────
  function recomputeCounts() {
    var totalChecked = 0;
    var totalAll = 0;

    $('#rpPanel tr[data-module]').each(function () {
      var slug = $(this).data('module');
      var boxes = $('.' + slug + '-checkbox');
      var checked = boxes.filter(':checked').length;
      var all = boxes.length;
      totalChecked += checked;
      totalAll += all;

      var $selectAll = $('.rp-module-select-all[data-module="' + slug + '"]');
      if ($selectAll.length) {
        $selectAll.prop('checked', all > 0 && checked === all);
        $selectAll[0].indeterminate = checked > 0 && checked < all;
      }
    });

    $('#rpSaveCount').text(totalChecked);
    $('#rpGrantAll').prop('checked', totalAll > 0 && totalChecked === totalAll);
  }

  $(document).on('change', '.rp-permission-checkbox', recomputeCounts);

  $(document).on('change', '.rp-module-select-all', function () {
    var slug = $(this).data('module');
    $('.' + slug + '-checkbox').prop('checked', $(this).is(':checked'));
    recomputeCounts();
  });

  $(document).on('change', '#rpGrantAll', function () {
    $('#rpPanel .rp-permission-checkbox').prop('checked', $(this).is(':checked'));
    recomputeCounts();
  });

  // ── Panel: tabs ──────────────────────────────────────────────────────
  $(document).on('click', '.rp-tab', function () {
    $('.rp-tab').removeClass('rp-tab-active');
    $(this).addClass('rp-tab-active');
    var tab = $(this).data('tab');
    $('#rpTabModules').toggleClass('d-none', tab !== 'modules');
    $('#rpTabAdvanced').toggleClass('d-none', tab !== 'advanced');
    $('#rpTabUsers').toggleClass('d-none', tab !== 'users');
  });

  // ── Panel: search across modules + permission labels ────────────────
  $(document).on('keyup', '#rpSearch', function () {
    var q = $.trim($(this).val()).toLowerCase();
    if (!q) {
      $('#rpPanel [data-search]').removeClass('rp-hidden-by-search');
      return;
    }
    $('#rpPanel tr[data-module]').each(function () {
      var $row = $(this);
      var moduleMatches = ($row.data('search') || '').toString().indexOf(q) !== -1;
      var anyChipMatch = false;
      $row.find('.rp-chip').each(function () {
        var chipMatches = moduleMatches || ($(this).data('search') || '').toString().indexOf(q) !== -1;
        $(this).toggleClass('rp-hidden-by-search', !chipMatches);
        if (chipMatches) anyChipMatch = true;
      });
      $row.toggleClass('rp-hidden-by-search', !(moduleMatches || anyChipMatch));
    });
  });

  // ── Panel: persona template ──────────────────────────────────────────
  $(document).on('change', '#rpPreset', function () {
    var opt = this.options[this.selectedIndex];
    var $hint = $('#rpPresetHint');
    if (!this.value) { $hint.text(''); return; }
    var perms = (opt.getAttribute('data-permissions') || '').split(',').filter(Boolean);
    $('#rpPanel .rp-permission-checkbox').prop('checked', false);
    perms.forEach(function (p) {
      $('#rpPanel .rp-permission-checkbox[value="' + p + '"]').prop('checked', true);
    });
    recomputeCounts();
    $hint.text((opt.getAttribute('title') || '') + '  (' + perms.length + ' permissions applied — adjust below if needed)');
  });

  // ── Panel: copy permissions from another role ───────────────────────
  $(document).on('change', '#rpCopyFromRole', function () {
    var sourceId = $(this).val();
    if (!sourceId) return;
    var $select = $(this);
    $.get(`${baseUrl}roles/${sourceId}/getRolePermissionsAjax`, function (res) {
      if (!res || res.status !== 'success') return;
      $('#rpPanel .rp-permission-checkbox').prop('checked', false);
      res.data.forEach(function (name) {
        $('#rpPanel .rp-permission-checkbox[value="' + name + '"]').prop('checked', true);
      });
      recomputeCounts();
      showSuccessToast('Copied ' + res.data.length + ' permissions — remember to Save');
      $select.val('');
    });
  });

  // ── Panel: delete role ───────────────────────────────────────────────
  $(document).on('click', '.rp-delete-role', function () {
    window.deleteRole($(this).data('id'));
  });

  // ── Panel: discard (reload this role's panel fresh from the server) ─
  $(document).on('click', '#rpDiscardBtn', function () {
    if (currentRoleId) loadRolePanel(currentRoleId);
  });

  // ── Panel: save ──────────────────────────────────────────────────────
  $(document).on('click', '#rpSaveBtn', function () {
    var name = $.trim($('#rpRoleName').val());
    if (!name) {
      Swal.fire({ icon: 'warning', title: 'Role name required', text: 'Give this role a name before saving.' });
      return;
    }
    var $btn = $(this).prop('disabled', true);
    $.ajax({
      url: `${baseUrl}roles/addOrUpdateAjax`,
      type: 'POST',
      data: $('#rpForm').serialize(),
      success: function () {
        showSuccessToast('Role permissions saved successfully');
        if (currentRoleId) loadRolePanel(currentRoleId);
        $btn.prop('disabled', false);
      },
      error: function (err) {
        $btn.prop('disabled', false);
        var response = err.responseJSON;
        Swal.fire({
          title: 'Failed',
          text: response && response.data ? response.data : 'Forbidden (403) or session expired. Please refresh.',
          icon: 'error',
          customClass: { confirmButton: 'btn btn-success' }
        });
      }
    });
  });

  // ── Create New Role modal ────────────────────────────────────────────
  var roleAdd = document.querySelector('.add-new-role');
  var offCanvasForm = $('#addRoleForm');
  var addRoleModel = $('#addOrUpdateRoleModal');

  $(document).on('change', '#rolePreset', function () {
    var selected = this.options[this.selectedIndex];
    $('#rolePresetHint').text(this.value ? (selected.getAttribute('title') || '') : '');
  });

  roleAdd.onclick = function () {
    $('#name').val('');
    $('#description').val('');
    $('#rolePreset').val('');
    $('#rolePresetHint').text('');
  };

  (function () {
    FormValidation.formValidation(document.getElementById('addRoleForm'), {
      fields: {
        name: { validators: { notEmpty: { message: 'Please enter role name' } } }
      },
      plugins: {
        trigger: new FormValidation.plugins.Trigger(),
        bootstrap5: new FormValidation.plugins.Bootstrap5({ eleValidClass: '', rowSelector: '.mb-4, .mb-1' }),
        submitButton: new FormValidation.plugins.SubmitButton(),
        autoFocus: new FormValidation.plugins.AutoFocus()
      }
    }).on('core.form.valid', function () {
      $.ajax({
        data: $('#addRoleForm').serialize(),
        url: `${baseUrl}roles/addOrUpdateAjax`,
        type: 'POST',
        success: function (status) {
          addRoleModel.modal('hide');
          offCanvasForm.trigger('reset');
          showSuccessToast('Role created — configure its access next');
          var newRoleId = status && status.data && status.data.id;
          setTimeout(() => {
            window.location.href = newRoleId ? `${baseUrl}roles?role=${newRoleId}` : location.href;
          }, 800);
        },
        error: function (err) {
          var response = err.responseJSON;
          Swal.fire({
            title: 'Failed',
            text: response && response.data ? response.data : 'Forbidden (403) or Session Expired. Please refresh.',
            icon: 'error',
            customClass: { confirmButton: 'btn btn-success' }
          });
        }
      });
    });
  })();

  window.deleteRole = function (id) {
    Swal.fire({
      title: 'Are you sure?',
      text: "You won't be able to revert this!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!',
      customClass: { confirmButton: 'btn btn-primary me-3', cancelButton: 'btn btn-label-secondary' },
      buttonsStyling: false
    }).then(function (result) {
      if (result.value) {
        $.ajax({
          type: 'DELETE',
          url: `${baseUrl}roles/deleteAjax/${id}`,
          success: function () {
            Swal.fire({
              icon: 'success', title: 'Deleted!', text: 'The role has been deleted!',
              customClass: { confirmButton: 'btn btn-success' }
            }).then(function () { location.reload(); });
          },
          error: function (error) {
            var response = error.responseJSON;
            Swal.fire({ icon: 'error', title: 'Oops...', text: response.data, customClass: { confirmButton: 'btn btn-success' } });
          }
        });
      }
    });
  };
});
