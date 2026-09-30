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

  // Module -> [boxicon, colour]; anything unlisted gets a neutral folder.
  var MODULE_ICON = {
    'Dashboard': ['bxs-home', '#2563eb'], 'Announcements': ['bxs-megaphone', '#9333ea'],
    'Employees': ['bxs-user-detail', '#0d9488'], 'Attendance': ['bxs-time-five', '#2563eb'],
    'Leaves': ['bxs-calendar-minus', '#f59e0b'], 'Travel & Expense': ['bxs-plane-alt', '#f97316'],
    'Payroll': ['bxs-wallet', '#9333ea'], 'Recruitment & Onboarding': ['bxs-user-plus', '#2563eb'],
    'Job Postings': ['bxs-briefcase', '#0d9488'], 'Job Applications': ['bxs-file', '#6366f1'],
    'Job Categories & Stages': ['bxs-layer', '#8b5cf6'], 'Interview & Screening': ['bxs-chat', '#06b6d4'],
    'Hierarchy & Probation': ['bx-git-branch', '#64748b'], 'Sales Field Ops': ['bxs-map', '#e11d48'],
    'Telephony': ['bxs-phone-call', '#16a34a'], 'CCare Attendance': ['bxs-headphone', '#0ea5e9'],
    'Intelligent Vault': ['bxs-lock-alt', '#475569'], 'Asset Management': ['bxs-box', '#ca8a04'],
    'Monitoring': ['bxs-map-pin', '#dc2626'], 'Kingo Bingo': ['bxs-trophy', '#f59e0b'],
    'Performance': ['bx-line-chart', '#2563eb'], 'Loans & Advances': ['bxs-bank', '#0d9488'],
    'Reports': ['bxs-report', '#f97316'], 'Settings & Administration': ['bxs-cog', '#475569'],
    'Configuration Masters': ['bxs-data', '#6366f1'], 'Operations': ['bxs-wrench', '#64748b'],
    'System Utilities': ['bxs-server', '#475569'], 'Training': ['bxs-graduation', '#9333ea']
  };
  function moduleIcon(name) {
    var ic = MODULE_ICON[name] || ['bxs-folder', '#64748b'];
    return '<span class="usp-mod-icon" style="background:' + ic[1] + '1a;color:' + ic[1] + '"><i class="bx ' + ic[0] + '"></i></span>';
  }

  function mark(kind, label) {
    var icon = { yes: 'bx-check', view: 'bx-check', partial: 'bx-minus', no: 'bx-x' }[kind];
    return '<span class="usp-mark"><span class="usp-mark-box usp-mark-' + kind + '"><i class="bx ' + icon + '"></i></span>' + label + '</span>';
  }

  // Role / Final column: Yes, View Only, "3 of 7", Page access, or No.
  // "Page access" = no action permissions here, but the module's sidebar
  // section still opens for them (e.g. every employee's Dashboard).
  function accessCell(m, key, count) {
    var pageOnly = m.sectionOpen && (key === 'final' || m.specialCount === 0);
    if (count === 0 && pageOnly) {
      return '<span title="Can open the ' + escapeHtml(m.section) + ' section; no extra actions in it">' + mark('view', 'Page access') + '</span>';
    }
    if (count === 0) return mark('no', 'No');
    if (count === m.total) return mark('yes', 'Yes');
    if (count === 1 && m.columns.view && m.columns.view[key]) return mark('view', 'View Only');
    return mark('partial', count + ' of ' + m.total);
  }

  // Special column: nothing extra reads as a dash, not a red "No".
  function specialCell(m) {
    if (m.specialCount === 0) return '<span class="usp-mark-dash">—</span>';
    return mark('yes', m.specialCount === 1 ? '1 added' : m.specialCount + ' added');
  }

  function sourceBadge(source, m) {
    if (source === 'none' && m && m.sectionOpen) return '<span class="usp-src">Role</span>';
    if (source === 'none') return '<span class="usp-mark-dash">—</span>';
    return '<span class="usp-src usp-src-' + source + '">' + SOURCE_LABEL[source] + '</span>';
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
      meta = unwired[c.name] ? 'No effect yet — nothing in the app checks this' : 'Tick to allow';
    }
    return '<label class="usp-eff-item' + (c.role ? ' usp-eff-locked' : '') + '"' + (c.role ? ' title="Their role already gives this — it can’t be removed per person"' : '') + '>' +
      '<input type="checkbox" class="usp-perm-check" value="' + escapeHtml(c.name) + '"' + attrs + '>' +
      '<span class="usp-eff-text"><span class="usp-eff-name">' + escapeHtml(c.label) + '</span>' +
      '<span class="usp-eff-meta d-block">' + meta + '</span></span>' + badge + '</label>';
  }

  var unwired = {};

  function renderTable(payload) {
    var log = payload.grantLog || {};
    unwired = {};
    (payload.unwired || []).forEach(function (n) { unwired[n] = true; });
    var rows = '';
    payload.modules.forEach(function (m, idx) {
      var rowId = 'usp-row-' + idx;
      var open = !!expanded[m.name];
      var cells = moduleCells(m);
      rows += '<tr class="usp-recon-row' + (open ? ' usp-expanded' : '') + '" data-module="' + escapeHtml(m.name) + '" data-target="' + rowId + '" data-search="' + escapeHtml(m.name.toLowerCase()) + '">';
      rows += '  <td><div class="usp-recon-module">' + moduleIcon(m.name) + '<span class="usp-mod-name">' + escapeHtml(m.name) + '</span><i class="bx bx-chevron-down usp-chev"></i></div></td>';
      rows += '  <td>' + accessCell(m, 'role', m.roleCount) + '</td>';
      rows += '  <td>' + specialCell(m) + '</td>';
      rows += '  <td>' + accessCell(m, 'final', m.finalCount) + '</td>';
      rows += '  <td class="text-center">' + sourceBadge(m.source, m) + '</td>';
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
