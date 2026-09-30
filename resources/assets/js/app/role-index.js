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

  // Force every checkbox/toggle to reflect what the server actually sent,
  // on this very first render too - not just after an AJAX role-switch.
  // Chrome/Edge restore a field's checked state across page loads by id,
  // independent of the HTML the server just rendered; on a long-lived tab
  // that's been through several role switches and reloads, a toggle like
  // "Grant Full Administrative Access" can end up visually checked while
  // the underlying data (and the count right next to it) says otherwise.
  // autocomplete="off" on these fields discourages that in the first
  // place; this call is the actual guarantee - it can't be fooled by
  // whatever the browser tried to restore, because it reads the real
  // .checked / :checked state of every box and derives the toggle from
  // that, not the other way around.
  recomputeCounts();
  initAccessChannelToggles();

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
      initAccessChannelToggles();
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

  // ── Advanced (manual permission) section: collapsed by default ───────
  $(document).on('click', '#rpAdvancedToggle', function () {
    $(this).toggleClass('rp-advanced-open');
    $('#rpAdvancedSection').toggleClass('d-none');
  });

  // ── Grant button on a hidden reachability item ────────────────────────
  // Ticks whichever of that item's alternative permissions actually have a
  // checkbox on this page (any one satisfies the underlying role_or_permission
  // check), opens Advanced so the change is visible, and leaves Save as the
  // explicit last step - this never writes anything by itself.
  $(document).on('click', '.rp-grant-btn', function () {
    var $btn = $(this);
    var perms = ($btn.data('permissions') || '').toString().split(',').filter(Boolean);
    if (!perms.length) return;

    $('#rpAdvancedSection').removeClass('d-none');
    $('#rpAdvancedToggle').addClass('rp-advanced-open');

    var $firstRow = null;
    perms.forEach(function (p) {
      var $cb = $('#rpPanel .rp-permission-checkbox[value="' + p + '"]');
      if ($cb.length) {
        $cb.prop('checked', true).trigger('change');
        if (!$firstRow) $firstRow = $cb.closest('tr, .rp-chip');
      }
    });

    $btn.addClass('rp-grant-done').html('<i class="bx bx-check"></i> Granted — click Save below');
    if ($firstRow && $firstRow.length) {
      $firstRow[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
      $firstRow.css('background', '#dcfce7');
      setTimeout(function () { $firstRow.css('background', ''); }, 1500);
    }
  });

  // A switch's ON/OFF look normally comes from the browser's own
  // `:checked + label` CSS matching. That's fine for an ordinary saved
  // setting, but #rpGrantAll isn't one - it's a value CLIENT-COMPUTED from
  // every other checkbox, and different browsers/extensions (ad-block /
  // shields-style content alteration, autofill heuristics, etc.) have been
  // seen to render a switch's :checked state inconsistently with its real
  // DOM property, with no server bug and nothing wrong in the data. Rather
  // than keep trusting that native rendering, this puts a class on the
  // wrapper that OUR code controls end to end, and roles/index.blade.php's
  // CSS paints strictly off that class (with !important, so it cannot lose
  // to the theme's own :checked rule either way).
  function syncSwitchVisual($checkbox, isOn) {
    $checkbox.prop('checked', isOn);
    $checkbox.closest('.hitech-toggle-wrapper').toggleClass('rp-switch-on', !!isOn);
  }

  // ── Panel: live permission counters ─────────────────────────────────
  // ✓ Yes / ■ "3 of 7" / ✓ Page access / ✗ No for one module row.
  // "Page access" = none of the module's action permissions, but its
  // sidebar section still opens for this role.
  function markHtml(checked, all, sectionOpen) {
    var kind, label;
    if (all > 0 && checked === all) { kind = 'yes'; label = 'Yes'; }
    else if (checked > 0) { kind = 'partial'; label = checked + ' of ' + all; }
    else if (sectionOpen) { kind = 'view'; label = 'Page access'; }
    else { kind = 'no'; label = 'No'; }
    var icon = { yes: 'bx-check', view: 'bx-check', partial: 'bx-minus', no: 'bx-x' }[kind];
    return '<span class="rp-mark"><span class="rp-mark-box rp-mark-' + kind + '"><i class="bx ' + icon + '"></i></span>' + label + '</span>';
  }

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
      $(this).find('.rp-mod-mark').html(markHtml(checked, all, String($(this).data('section-open')) === '1'));

      var $selectAll = $('.rp-module-select-all[data-module="' + slug + '"]');
      if ($selectAll.length) {
        $selectAll.prop('checked', all > 0 && checked === all);
        $selectAll[0].indeterminate = checked > 0 && checked < all;
        // Nothing left to toggle when every box in the row is locked.
        $selectAll.prop('disabled', all > 0 && boxes.filter(':disabled').length === all);
      }
    });

    $('#rpSaveCount').text(totalChecked);
    $('#rpHeadPerms').text(totalChecked);
    syncSwitchVisual($('#rpGrantAll'), totalAll > 0 && totalChecked === totalAll);
  }

  // The four access-channel switches carry their own real, saved value -
  // not derived from the permission grid, so (unlike #rpGrantAll) they must
  // NOT be re-asserted every time recomputeCounts() runs for an unrelated
  // reason, or a manual click here would get silently reverted the next
  // time any permission checkbox changes. This seeds each one from the
  // data-actual the server rendered, once per panel render (initial load
  // and after each AJAX role-switch); a direct click after that is handled
  // by its own change listener below, which only ever mirrors that one
  // switch's own new state.
  function initAccessChannelToggles() {
    ['rpMultiCheckIn', 'rpMobileApp', 'rpWebLogin', 'rpGpsTracking'].forEach(function (id) {
      var $el = $('#' + id);
      if (!$el.length) return;
      syncSwitchVisual($el, String($el.attr('data-actual')) === '1');
    });
  }

  $(document).on('change', '.rp-permission-checkbox', recomputeCounts);

  $(document).on('change', '.rp-module-select-all', function () {
    var slug = $(this).data('module');
    $('.' + slug + '-checkbox:not(:disabled)').prop('checked', $(this).is(':checked'));
    recomputeCounts();
  });

  $(document).on('change', '#rpGrantAll', function () {
    $('#rpPanel .rp-permission-checkbox:not(:disabled)').prop('checked', $(this).is(':checked'));
    recomputeCounts();
  });

  $(document).on('change', '#rpMultiCheckIn, #rpMobileApp, #rpWebLogin, #rpGpsTracking', function () {
    syncSwitchVisual($(this), this.checked);
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

  // ── Panel: module rows open/close to their permission checkboxes ─────
  function setModuleOpen($row, open) {
    $row.toggleClass('rp-mod-open', open);
    $('#rpPanel tr[data-detail-for="' + $row.data('module') + '"]').toggleClass('d-none', !open);
  }

  $(document).on('click', '#rpPanel .rp-mod-row', function (e) {
    if ($(e.target).closest('label, input').length) return; // the Select All box
    setModuleOpen($(this), !$(this).hasClass('rp-mod-open'));
  });

  $(document).on('click', '#rpExpandAll, #rpCollapseAll', function () {
    var open = this.id === 'rpExpandAll';
    $('#rpPanel .rp-mod-row:not(.rp-hidden-by-search)').each(function () { setModuleOpen($(this), open); });
  });

  // ── Panel: search module names and permission labels ────────────────
  // A match inside a module opens it so the matching box is visible.
  $(document).on('keyup', '#rpSearch', function () {
    var q = $.trim($(this).val()).toLowerCase();
    $('#rpPanel .rp-mod-row').each(function () {
      var $row = $(this);
      var $detail = $('#rpPanel tr[data-detail-for="' + $row.data('module') + '"]');
      var inName = !q || ($row.data('search') || '').toString().indexOf(q) !== -1;
      var inPerms = !!q && ($detail.data('search') || '').toString().indexOf(q) !== -1;
      $row.toggleClass('rp-hidden-by-search', !(inName || inPerms));
      $detail.toggleClass('rp-hidden-by-search', !(inName || inPerms));
      if (inPerms) setModuleOpen($row, true);
    });
  });

  // ── Panel: persona template ──────────────────────────────────────────
  $(document).on('change', '#rpPreset', function () {
    var opt = this.options[this.selectedIndex];
    var $hint = $('#rpPresetHint');
    if (!this.value) { $hint.text(''); return; }
    var perms = (opt.getAttribute('data-permissions') || '').split(',').filter(Boolean);
    $('#rpPanel .rp-permission-checkbox:not(:disabled)').prop('checked', false);
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
      $('#rpPanel .rp-permission-checkbox:not(:disabled)').prop('checked', false);
      res.data.forEach(function (name) {
        $('#rpPanel .rp-permission-checkbox[value="' + name + '"]').prop('checked', true);
      });
      // Locked boxes stay ticked whatever the source role had.
      recomputeCounts();
      showSuccessToast('Copied ' + res.data.length + ' permissions — remember to Save');
      $select.val('');
    });
  });

  // ── Panel: "see everything this role can reach" -> Preview Access ────
  $(document).on('click', '.rp-preview-link', function () {
    var roleName = $(this).data('role');
    $('#previewTarget').val('role:' + roleName);
    $('#previewAccessModal').modal('show');
    $('#runPreview').trigger('click');
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
