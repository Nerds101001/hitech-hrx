/**
 * User Special Permissions — dedicated page JS.
 * One table is the whole page: each module row expands to its permissions,
 * each with a checkbox. From the role = ticked + locked; special = ticked,
 * untick to remove; not granted = tick to allow. No side panel, no popup.
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
  });

  var SOURCE_LABEL = { role: 'Role', special: 'Special', both: 'Role + Special', none: '—' };
  var currentUserId = null;
  var currentPayload = null;
  var expanded = {}; // module name -> open, kept across reloads of the same person

  function escapeHtml(str) {
    return $('<div>').text(str == null ? '' : str).html();
  }

  // "3 / 5" - action permissions held out of those in the module.
  function countPill(n, total) {
    var state = n === 0 ? 'none' : (n === total ? 'full' : 'partial');
    return '<span class="usp-count usp-state-' + state + '">' + n + ' / ' + total + '</span>';
  }

  // Every permission cell of one module, CRUD columns first.
  function moduleCells(m) {
    return ['view', 'create', 'edit', 'delete'].map(function (v) { return m.columns[v]; }).filter(Boolean).concat(m.other);
  }

  // One permission, with the only control this page has: its checkbox.
  function permissionItem(c, log) {
    var badge, meta = '', attrs = '';
    if (c.role) {
      badge = '<span class="usp-eff-badge usp-eff-badge-role">Role</span>';
      attrs = ' checked disabled';
      meta = 'From their role';
    } else if (c.special) {
      badge = '<span class="usp-eff-badge usp-eff-badge-special">Special</span>';
      attrs = ' checked';
      meta = log[c.name] ? 'Added by ' + escapeHtml(log[c.name].by) + ' · ' + escapeHtml(log[c.name].at) : 'Untick to remove';
    } else {
      badge = '<span class="usp-eff-badge usp-eff-badge-none">Not granted</span>';
      meta = 'Tick to allow';
    }
    return '<label class="usp-eff-item' + (c.role ? ' usp-eff-locked' : '') + '"' + (c.role ? ' title="Their role already gives this — it can’t be removed per person"' : '') + '>' +
      '<input type="checkbox" class="usp-perm-check" value="' + escapeHtml(c.name) + '"' + attrs + '>' +
      '<span class="usp-eff-text"><span class="usp-eff-name">' + escapeHtml(c.label) + '</span>' +
      '<span class="usp-eff-meta d-block">' + meta + '</span></span>' + badge + '</label>';
  }

  function renderTable(payload) {
    var log = payload.grantLog || {};
    var rows = '';
    payload.modules.forEach(function (m, idx) {
      var rowId = 'usp-row-' + idx;
      var open = !!expanded[m.name];
      var cells = moduleCells(m);
      rows += '<tr class="usp-recon-row' + (open ? ' usp-expanded' : '') + '" data-module="' + escapeHtml(m.name) + '" data-target="' + rowId + '" data-search="' + escapeHtml(m.name.toLowerCase()) + '">';
      rows += '  <td><div class="usp-recon-module"><i class="bx bx-chevron-right"></i><i class="bx bx-folder text-primary"></i>' + escapeHtml(m.name) + '</div></td>';
      rows += '  <td class="text-center">' + countPill(m.roleCount, m.total) + '</td>';
      rows += '  <td class="text-center">' + countPill(m.specialCount, m.total) + '</td>';
      rows += '  <td class="text-center">' + countPill(m.finalCount, m.total) + '</td>';
      rows += '  <td class="text-center"><span class="usp-source-badge">' + SOURCE_LABEL[m.source] + '</span></td>';
      rows += '</tr>';
      rows += '<tr class="usp-detail-row' + (open ? '' : ' d-none') + '" id="' + rowId + '" data-search="' + escapeHtml(cells.map(function (c) { return c.label.toLowerCase(); }).join(' ')) + '">';
      rows += '  <td colspan="5"><div class="usp-detail-grid">' + cells.map(function (c) { return permissionItem(c, log); }).join('') + '</div></td>';
      rows += '</tr>';
    });
    $('#uspReconBody').html(rows);
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

  $(document).on('change', '.usp-perm-check', function () {
    var $cb = $(this);
    var grant = $cb.is(':checked');
    $cb.prop('disabled', true);
    togglePermission($cb.val(), grant, function () { $cb.prop('checked', !grant).prop('disabled', false); });
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
    $('#uspSpecialCount').text(payload.summary.specialOnly);
    $('#uspLastChange').text(payload.lastChange
      ? 'Last change: ' + payload.lastChange.text + ' by ' + payload.lastChange.by + ', ' + payload.lastChange.at
      : 'Given to this person only, on top of the role');
    $('#uspGuardWarning').toggleClass('d-none', !payload.guardWarning);
    $('#uspGuardWarningText').text(payload.guardWarning || '');

    var s = payload.summary;
    var reach = payload.reach || [];
    $('#uspHeadPages').text(reach.filter(function (i) { return i.visible; }).length + ' of ' + reach.length);
    $('#uspHeadPerms').text(s.finalPermissions + ' of ' + s.totalPermissions);
    $('#uspHeadPermsSub').text(payload.isSuper
      ? 'Admin — everything, by role'
      : s.fromRole + ' from their role + ' + s.specialOnly + ' special');

    renderTable(payload);
    renderAdvanced(payload.role);
  }

  function loadEmployee(userId) {
    if (userId !== currentUserId) expanded = {}; // a new person starts collapsed
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

  // Module row: open/close its permissions.
  $(document).on('click', '.usp-recon-row', function () {
    var name = $(this).data('module');
    expanded[name] = !expanded[name];
    $(this).toggleClass('usp-expanded', expanded[name]);
    $('#' + $(this).data('target')).toggleClass('d-none', !expanded[name]);
  });

  $(document).on('click', '#uspExpandAll, #uspCollapseAll', function () {
    var open = this.id === 'uspExpandAll';
    $('.usp-recon-row:not(.usp-hidden-by-search)').each(function () {
      expanded[$(this).data('module')] = open;
      $(this).toggleClass('usp-expanded', open);
      $('#' + $(this).data('target')).toggleClass('d-none', !open);
    });
  });

  // Search modules and permission names; a permission match opens its module.
  $('#uspTableSearch').on('keyup', function () {
    var q = $.trim($(this).val()).toLowerCase();
    $('.usp-recon-row').each(function () {
      var $row = $(this);
      var $detail = $('#' + $row.data('target'));
      var inName = !q || ($row.data('search') || '').toString().indexOf(q) !== -1;
      var inPerms = !!q && ($detail.data('search') || '').toString().indexOf(q) !== -1;
      $row.toggleClass('usp-hidden-by-search', !(inName || inPerms));
      $detail.toggleClass('usp-hidden-by-search', !(inName || inPerms));
      if (inPerms) { $row.addClass('usp-expanded'); $detail.removeClass('d-none'); }
    });
  });

  // Preview Access — reuse the shared modal/handler from preview-access.js
  $('#uspPreviewBtn').on('click', function () {
    if (!currentUserId) return;
    $('#previewTarget').val('user:' + currentUserId);
    $('#previewAccessModal').modal('show');
    $('#runPreview').trigger('click');
  });

  // Land on the first employee so the page is never an empty shell.
  var $first = $('.usp-emp-row').first();
  if ($first.length) loadEmployee($first.data('id'));
});
