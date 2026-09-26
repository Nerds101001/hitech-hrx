/**
 * User Special Permissions — dedicated page JS.
 * Owns: employee picker, the role/special/final reconciliation table, and
 * the edit modal (moved here from role-index.js — this page is the only
 * place that modal is triggered from now).
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
  });

  var STATE_LABEL = { full: 'Full Access', view_only: 'View Only', partial: 'Partial', none: 'Not Granted' };
  var SOURCE_LABEL = { role: 'Role', special: 'Special', both: 'Role + Special', none: '—' };
  var currentUserId = null;
  var currentPayload = null;

  function escapeHtml(str) {
    return $('<div>').text(str == null ? '' : str).html();
  }

  function statePill(state) {
    return '<span class="usp-state-pill usp-state-' + state + '">' + STATE_LABEL[state] + '</span>';
  }

  function tag(has, label) {
    return '<span class="usp-tag ' + (has ? 'usp-tag-' + label.toLowerCase() : 'usp-tag-none') + '">' + (has ? label : '—') + '</span>';
  }

  function renderDetailRow(cell) {
    if (!cell) return '';
    return '<div class="usp-detail-item">' +
      '<span class="usp-detail-label">' + escapeHtml(cell.label) + '</span>' +
      '<span class="usp-detail-tags">' + tag(cell.role, 'Role') + tag(cell.special, 'Special') + '</span>' +
      '</div>';
  }

  function renderReconciliation(payload) {
    var rows = '';
    payload.modules.forEach(function (m, idx) {
      var rowId = 'usp-row-' + idx;
      rows += '<tr class="usp-recon-row" data-idx="' + idx + '" data-target="' + rowId + '" data-search="' + escapeHtml(m.name.toLowerCase()) + '">';
      rows += '  <td><div class="usp-recon-module"><i class="bx bx-chevron-right"></i><i class="bx bx-folder text-primary"></i>' + escapeHtml(m.name) + '</div></td>';
      rows += '  <td class="text-center">' + statePill(m.roleState) + '</td>';
      rows += '  <td class="text-center">' + statePill(m.specialState) + '</td>';
      rows += '  <td class="text-center">' + statePill(m.finalState) + '</td>';
      rows += '  <td class="text-center"><span class="usp-source-badge">' + SOURCE_LABEL[m.source] + '</span></td>';
      rows += '</tr>';

      var detailCells = ['view', 'create', 'edit', 'delete'].map(function (v) { return m.columns[v]; }).filter(Boolean);
      detailCells = detailCells.concat(m.other);
      var searchBits = detailCells.map(function (c) { return c.label.toLowerCase(); }).join(' ');

      rows += '<tr class="usp-detail-row d-none" id="' + rowId + '" data-search="' + escapeHtml(searchBits) + '">';
      rows += '  <td colspan="5"><div class="usp-detail-grid">' + detailCells.map(renderDetailRow).join('') + '</div></td>';
      rows += '</tr>';
    });
    $('#uspReconBody').html(rows);
  }

  // Every permission cell of one module, CRUD columns first.
  function moduleCells(m) {
    return ['view', 'create', 'edit', 'delete'].map(function (v) { return m.columns[v]; }).filter(Boolean).concat(m.other);
  }

  // ── Effective Permissions panel: one module, tick to grant/remove ─────
  var currentModuleIdx = null;

  function renderEffPanel(idx) {
    var payload = currentPayload;
    if (!payload || !payload.modules[idx]) return;
    currentModuleIdx = idx;
    $('#uspEffModule').val(String(idx));
    $('.usp-recon-row').removeClass('usp-row-selected');
    $('.usp-recon-row[data-idx="' + idx + '"]').addClass('usp-row-selected');

    var log = payload.grantLog || {};
    var html = moduleCells(payload.modules[idx]).map(function (c) {
      var badge, meta = '', attrs = '';
      if (c.role) {
        badge = '<span class="usp-eff-badge usp-eff-badge-role">Role</span>';
        attrs = ' checked disabled title="Their role already gives this"';
        meta = 'From their role — can’t be changed per person';
      } else if (c.special) {
        badge = '<span class="usp-eff-badge usp-eff-badge-special">Special</span>';
        attrs = ' checked';
        meta = log[c.name] ? 'Added by ' + escapeHtml(log[c.name].by) + ' · ' + escapeHtml(log[c.name].at) : 'Added before change logging began';
      } else {
        badge = '<span class="usp-eff-badge usp-eff-badge-none">Not Granted</span>';
      }
      return '<label class="usp-eff-item">' +
        '<input type="checkbox" class="usp-eff-check" value="' + escapeHtml(c.name) + '"' + attrs + '>' +
        '<span class="usp-eff-text"><span class="usp-eff-name">' + escapeHtml(c.label) + '</span>' +
        (meta ? '<span class="usp-eff-meta d-block">' + meta + '</span>' : '') + '</span>' +
        badge + '</label>';
    }).join('');
    $('#uspEffList').html(html || '<p class="text-muted small mb-0">No permissions in this module.</p>');
  }

  function renderEffSelect(payload) {
    var opts = payload.modules.map(function (m, i) {
      return '<option value="' + i + '">' + escapeHtml(m.name) + '</option>';
    }).join('');
    $('#uspEffModule').html(opts);
    // Keep the module the admin was on across reloads; else the first with a special grant.
    var idx = currentModuleIdx;
    if (idx === null || !payload.modules[idx]) {
      idx = payload.modules.findIndex(function (m) { return m.specialState !== 'none'; });
      if (idx < 0) idx = 0;
    }
    renderEffPanel(idx);
  }

  // ── What this person can actually reach ──────────────────────────────
  function renderReach(payload) {
    var tree = payload.reach || [];
    var visible = tree.filter(function (i) { return i.visible; }).length;
    $('#uspReachStat').text(visible + ' / ' + tree.length);
    var html = tree.map(function (item) {
      var reason = '';
      if (!item.visible && item.reason) {
        var btn = (item.grantable && item.grantable.length)
          ? '<button type="button" class="usp-grant-btn" data-permissions="' + escapeHtml(item.grantable.join(',')) + '" data-section="' + escapeHtml(item.name) + '"><i class="bx bx-plus-circle"></i> Grant</button>'
          : '';
        reason = '<div class="pmt-reason">' + escapeHtml(item.reason) + btn + '</div>';
      }
      return '<div class="pmt-item ' + (item.visible ? 'pmt-visible' : 'pmt-hidden') + '">' +
        '<div class="pmt-row"><div class="pmt-icon"><i class="' + escapeHtml(item.icon || 'bx bx-square') + '"></i></div>' +
        '<div class="pmt-name">' + escapeHtml(item.name) + '</div>' +
        '<span class="pmt-badge ' + (item.visible ? 'pmt-badge-on' : 'pmt-badge-off') + '">' + (item.visible ? 'VISIBLE' : 'HIDDEN') + '</span></div>' +
        reason + '</div>';
    }).join('');
    $('#uspReachGrid').html(html);
  }

  // One special permission on/off for the current person.
  function togglePermission(permission, grant, onFail) {
    $.post(`${baseUrl}roles/toggleUserPermissionAjax`, { user_id: currentUserId, permission: permission, grant: grant ? 1 : 0 })
      .done(function (res) {
        if (res && res.status === 'success') {
          showSuccessToast(res.data);
          loadEmployee(currentUserId);
        } else {
          if (onFail) onFail();
          Swal.fire({ icon: 'error', title: 'Not changed', text: (res && res.data) || 'Could not save.' });
        }
      })
      .fail(function (err) {
        if (onFail) onFail();
        var r = err.responseJSON;
        Swal.fire({ icon: 'error', title: 'Not changed', text: (r && (r.data || r.message)) || 'Forbidden (403) or session expired. Please refresh.' });
      });
  }

  $(document).on('change', '#uspEffModule', function () {
    renderEffPanel(parseInt($(this).val(), 10));
  });

  $(document).on('change', '.usp-eff-check', function () {
    var $cb = $(this);
    var grant = $cb.is(':checked');
    $cb.prop('disabled', true);
    togglePermission($cb.val(), grant, function () { $cb.prop('checked', !grant).prop('disabled', false); });
  });

  // Grant on a hidden section: pick which of its accepted permissions to give.
  $(document).on('click', '.usp-grant-btn', function () {
    var perms = ($(this).data('permissions') || '').toString().split(',').filter(Boolean);
    var section = $(this).data('section');
    if (!perms.length) return;
    var options = {};
    perms.forEach(function (p) { options[p] = p; });
    Swal.fire({
      title: 'Grant access to ' + section,
      text: 'Any one of these opens it. Pick the narrowest that fits.',
      input: 'select',
      inputOptions: options,
      inputValue: perms[0],
      showCancelButton: true,
      confirmButtonText: 'Grant',
      customClass: { confirmButton: 'btn btn-primary me-3', cancelButton: 'btn btn-label-secondary' },
      buttonsStyling: false
    }).then(function (result) {
      if (result.isConfirmed && result.value) togglePermission(result.value, true);
    });
  });

  function renderAdvanced(role) {
    if (!role) { $('#uspAdvancedGrid').html('<p class="text-muted">No role assigned.</p>'); return; }
    var items = [
      ['Multi Check-In', role.multiCheckIn],
      ['Mobile App', role.mobileApp],
      ['Web Login', role.webLogin],
      ['GPS Tracking', role.gpsTracking],
    ];
    var html = items.map(function (i) {
      return '<div class="col-md-3"><div class="usp-detail-item">' +
        '<span class="usp-detail-label">' + i[0] + '</span>' +
        '<span class="usp-tag ' + (i[1] ? 'usp-tag-role' : 'usp-tag-none') + '">' + (i[1] ? 'Enabled' : 'Off') + '</span>' +
        '</div></div>';
    }).join('');
    $('#uspAdvancedGrid').html(html);
  }

  function renderEmployee(payload) {
    currentPayload = payload;
    $('#uspEmpty').addClass('d-none');
    $('#uspDetail').removeClass('d-none');
    $('#uspSummaryCard').show();

    var u = payload.user;
    $('#uspAvatarWrap').html(u.avatar
      ? '<img class="usp-profile-avatar" src="' + u.avatar + '" alt="">'
      : '<span class="usp-profile-avatar-fallback">' + escapeHtml(u.initials) + '</span>');
    $('#uspName').text(u.name);
    $('#uspStatusBadge').text(u.statusLabel);
    $('#uspRoleLabel').text(payload.role ? payload.role.name : 'No Role');
    $('#uspEmail').text(u.email);
    $('#uspPhone').text(u.phone || '—');
    $('#uspProfileLink').attr('href', u.profileUrl);

    if (payload.role) {
      $('#uspRoleName').text(payload.role.name);
      $('#uspRoleDesc').text(payload.role.description || 'No description set.');
      $('#uspRoleUrl').attr('href', payload.role.url).removeClass('disabled');
    } else {
      $('#uspRoleName').text('No Role');
      $('#uspRoleDesc').text('');
      $('#uspRoleUrl').attr('href', '#').addClass('disabled');
    }
    $('#uspSpecialCount').text(payload.specialCount);
    $('#uspLastChange').text(payload.lastChange
      ? 'Last change: ' + payload.lastChange.text + ' by ' + payload.lastChange.by + ', ' + payload.lastChange.at
      : 'Added directly, on top of the role');
    $('.usp-reach-name').text(u.name);
    $('#uspGuardWarning').toggleClass('d-none', !payload.guardWarning);
    $('#uspGuardWarningText').text(payload.guardWarning || '');
    $('#uspEffCard').show();

    var s = payload.summary;
    $('#sumTotal').text(s.totalModules);
    $('#sumFull').text(s.fullAccess);
    $('#sumView').text(s.viewOnly);
    $('#sumPartial').text(s.partial);
    $('#sumNone').text(s.notGranted);
    $('#sumSpecial').text(payload.specialCount);

    renderReconciliation(payload);
    renderAdvanced(payload.role);
    renderReach(payload);
    renderEffSelect(payload);
  }

  function loadEmployee(userId) {
    if (userId !== currentUserId) currentModuleIdx = null; // a new person starts on their own first module
    currentUserId = userId;
    $('.usp-emp-row').removeClass('usp-emp-active');
    $('.usp-emp-row[data-id="' + userId + '"]').addClass('usp-emp-active');

    $.get(`${baseUrl}roles/specialPermissionsDataAjax/${userId}`, function (res) {
      if (res && res.status === 'success') renderEmployee(res.data);
    });
  }

  // Employee list: search + select
  $('#uspEmpSearch').on('keyup', function () {
    var q = $.trim($(this).val()).toLowerCase();
    $('.usp-emp-row').each(function () {
      var matches = !q || ($(this).data('name') || '').toString().indexOf(q) !== -1;
      $(this).toggleClass('d-none', !matches);
    });
  });

  $(document).on('click', '.usp-emp-row', function () {
    loadEmployee($(this).data('id'));
  });

  // Tabs
  $(document).on('click', '.usp-tab', function () {
    $('.usp-tab').removeClass('usp-tab-active');
    $(this).addClass('usp-tab-active');
    var tab = $(this).data('tab');
    $('#uspTabModules').toggleClass('d-none', tab !== 'modules');
    $('#uspTabAdvanced').toggleClass('d-none', tab !== 'advanced');
  });

  // Reconciliation table: expand a module row for per-permission detail
  // ...and show that module in the Effective Permissions panel, where it can be changed.
  $(document).on('click', '.usp-recon-row', function () {
    $(this).toggleClass('usp-expanded');
    $('#' + $(this).data('target')).toggleClass('d-none');
    renderEffPanel(parseInt($(this).data('idx'), 10));
  });

  // Reconciliation table: search
  $('#uspTableSearch').on('keyup', function () {
    var q = $.trim($(this).val()).toLowerCase();
    $('.usp-recon-row').each(function () {
      var $row = $(this);
      var $detail = $('#' + $row.data('target'));
      var matches = !q
        || ($row.data('search') || '').toString().indexOf(q) !== -1
        || ($detail.data('search') || '').toString().indexOf(q) !== -1;
      $row.toggleClass('usp-hidden-by-search', !matches);
      if (!matches) $detail.addClass('d-none').removeClass('usp-hidden-by-search-open');
    });
  });

  // Preview Access — reuse the shared modal/handler from preview-access.js
  $('#uspPreviewBtn').on('click', function () {
    if (!currentUserId) return;
    $('#previewTarget').val('user:' + currentUserId);
    $('#previewAccessModal').modal('show');
    $('#runPreview').trigger('click');
  });

  // ── Edit Special Permissions modal (moved from role-index.js) ──────────
  var userPermModal = $('#addOrUpdateUserPermissionModal');
  var userPermForm = $('#addUserPermissionForm');

  function loadUserPermissionsIntoModal(userId) {
    $.get(`${baseUrl}roles/getUserPermissionsAjax/${userId}`, function (res) {
      $('.user-permission-checkbox, .user-row-select-all, #selectAllUser').prop('checked', false);
      if (res.data) {
        res.data.forEach(function (p) {
          $(`.user-permission-checkbox[value="${p.name}"]`).prop('checked', true);
        });
        $('.user-row-select-all').each(function () {
          var mod = $(this).data('module');
          var all = $(`.user-permission-checkbox.${mod}-checkbox:not(:checked)`).length === 0 && $(`.user-permission-checkbox.${mod}-checkbox`).length > 0;
          $(this).prop('checked', all);
        });
      }
    });
  }

  $('#user_id').on('change', function () {
    var userId = $(this).val();
    if (!userId) return;
    loadUserPermissionsIntoModal(userId);
  });

  $('#uspEditBtn').on('click', function () {
    if (!currentUserId) return;
    $('#user_id').val(currentUserId);
    loadUserPermissionsIntoModal(currentUserId);
    userPermModal.modal('show');
  });

  $(document).on('change', '.user-row-select-all', function () {
    var mod = $(this).data('module');
    $(`.${mod}-checkbox`).prop('checked', $(this).is(':checked'));
  });

  $('#selectAllUser').on('change', function () {
    $('.user-permission-checkbox, .user-row-select-all').prop('checked', $(this).is(':checked'));
  });

  userPermForm.on('submit', function (e) {
    e.preventDefault();
    $.ajax({
      url: `${baseUrl}roles/syncUserPermissionsAjax`,
      type: 'POST',
      data: $(this).serialize(),
      success: function (res) {
        userPermModal.modal('hide');
        showSuccessToast(res.data);
        if (currentUserId) loadEmployee(currentUserId);
      },
      error: function (err) {
        Swal.fire({ icon: 'error', title: 'Error', text: err.responseJSON.data });
      }
    });
  });

  // Land on the first employee so the page is never an empty shell.
  var $first = $('.usp-emp-row').first();
  if ($first.length) loadEmployee($first.data('id'));
});
