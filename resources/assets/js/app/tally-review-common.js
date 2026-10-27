'use strict';

// Shared Tally sync driver, mirroring window.SetuSync
// (setu-review-common.js) exactly, pointed at the sync-tally/resolve-tally
// endpoints and the tally-prefixed modal ids. Exposes window.TallySync.
window.TallySync = (function () {
  var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

  function csrf() { return $('meta[name="csrf-token"]').attr('content'); }
  function syncUrl(id) { return baseUrl + 'sales-visits/sales-report/sync-tally/' + id; }
  function resolveUrl(id) { return baseUrl + 'sales-visits/sales-report/resolve-tally/' + id; }
  function period() { return window.getTallyPeriod ? window.getTallyPeriod() : {}; }
  function esc(t) { return $('<div>').text(t == null ? '' : t).html(); }
  function fmtMonth(key) { var p = String(key).split('-'); return MONTHS[parseInt(p[1], 10) - 1] + ' ' + p[0].slice(2); }
  function postJson(url, body) {
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify(body) }).then(function (r) { return r.json(); });
  }

  var ctx = {};

  function run(userId, opts) {
    opts = opts || {};
    ctx = { userId: userId, period: period(), onDone: opts.onDone || function () {} };
    return postJson(syncUrl(userId), ctx.period).then(function (d) {
      if (d.status !== 'success') { throw new Error(d.data || 'Sync failed.'); }
      ctx.months = d.data.months;
      ctx.name = d.data.salesperson;
      renderPreview(d.data);
      return d.data;
    });
  }

  function chip(cls, n, label) { return '<span class="badge ' + cls + ' rounded-pill px-3 py-2">' + n + ' ' + label + '</span>'; }

  function renderPreview(data) {
    var c = data.counts;
    $('#tallyPreviewName').text(data.salesperson);
    $('#tallyPreviewPeriod').text(data.period);

    var chips = '';
    if (c.blankFills) chips += chip('bg-label-success', c.blankFills, 'blank fill' + (c.blankFills === 1 ? '' : 's'));
    if (c.overwrites) chips += chip('bg-label-warning', c.overwrites, 'correction' + (c.overwrites === 1 ? '' : 's'));
    if (c.newCustomers) chips += chip('bg-label-info', c.newCustomers, 'new customer' + (c.newCustomers === 1 ? '' : 's'));
    if (c.renames) chips += chip('bg-label-secondary', c.renames, 'name' + (c.renames === 1 ? '' : 's') + ' standardised');
    if (!chips) chips = '<span class="text-muted small">No automatic changes for this period.</span>';
    $('#tallyPreviewSummary').html(chips);

    var legend = '<div class="d-flex flex-wrap gap-3 small mb-2">'
      + '<span><span class="tally-cell tally-fill px-2">₹</span> blank filled</span>'
      + '<span><span class="tally-cell tally-over px-2">₹</span> corrected (hover for old)</span>'
      + '<span><span class="tally-cell tally-new px-2">₹</span> new customer</span>'
      + '<span><span class="tally-cell tally-review px-2">₹</span> needs your review</span>'
      + '<span class="text-muted">plain = unchanged</span></div>';
    var toggle = '<div class="form-check form-check-inline mb-2"><input class="form-check-input" type="checkbox" id="tallyChangedOnly"><label class="form-check-label small" for="tallyChangedOnly">Show only rows with changes</label></div>';

    var head = '<thead class="table-light" style="position:sticky;top:0;z-index:1;"><tr><th style="min-width:220px;">Customer</th>';
    data.months.forEach(function (mk) { head += '<th class="text-end small">' + fmtMonth(mk) + '</th>'; });
    head += '<th class="text-end">Total</th></tr></thead>';

    var body = '<tbody>';
    (data.allCustomers || []).forEach(function (cust) {
      var changed = false;
      var cellsHtml = '';
      data.months.forEach(function (mk) {
        var cell = cust.cells[mk];
        if (!cell || !cell.setu) { cellsHtml += '<td class="text-end text-muted small">·</td>'; return; }
        var cls = '', title = '';
        if (cell.change === 'fill') { cls = 'tally-fill'; changed = true; }
        else if (cell.change === 'overwrite') { cls = 'tally-over'; changed = true; title = ' title="was ₹' + Number(cell.hrx).toFixed(2) + 'L"'; }
        else if (cell.change === 'new') { cls = 'tally-new'; changed = true; }
        else if (cell.change === 'review') { cls = 'tally-review'; changed = true; }
        cellsHtml += '<td class="text-end small tally-cell ' + cls + '"' + title + '>₹' + Number(cell.setu).toFixed(2) + '</td>';
      });
      var badge = cust.status === 'new' ? '<span class="badge bg-label-info ms-1">new</span>'
        : cust.status === 'review' ? '<span class="badge bg-label-warning ms-1">review</span>' : '';
      var rename = cust.renamedFrom ? '<div class="small text-muted">was: ' + esc(cust.renamedFrom) + '</div>' : '';
      body += '<tr data-changed="' + (changed ? '1' : '0') + '"><td><span class="fw-semibold">' + esc(cust.customer) + '</span>' + badge + rename + '</td>'
        + cellsHtml + '<td class="text-end fw-semibold">₹' + Number(cust.total).toFixed(2) + 'L</td></tr>';
    });
    body += '</tbody>';

    var count = (data.allCustomers || []).length;
    var html = '<div class="d-flex justify-content-between align-items-end flex-wrap"><div>' + legend + '</div>' + toggle + '</div>'
      + '<div class="table-responsive" style="max-height:52vh;overflow:auto;"><table class="table table-sm table-bordered align-middle mb-0" id="tallyAllTable">' + head + body + '</table></div>'
      + '<div class="small text-muted mt-1">' + count + ' customer' + (count === 1 ? '' : 's') + ' found in Tally for this period.</div>';

    $('#tallyPreviewSections').html(count ? html : '<p class="text-muted">No customers found in Tally for this period.</p>');

    $('#tallyChangedOnly').on('change', function () {
      var only = $(this).is(':checked');
      $('#tallyAllTable tbody tr').each(function () { $(this).toggle(!only || $(this).data('changed') === 1); });
    });

    var canApply = c.blankFills || c.overwrites || c.renames || c.newCustomers;
    if (c.reviewItems) {
      $('#tallyPreviewReviewNote').show().html('<b>' + c.reviewItems + '</b> customer' + (c.reviewItems === 1 ? '' : 's') + ' also need a manual decision (a similar existing customer exists) — you\'ll resolve those' + (canApply ? ' right after applying' : ' next') + '.');
    } else {
      $('#tallyPreviewReviewNote').hide();
    }
    $('#tallyApplyChanges .label').text(canApply ? 'Apply Changes' : 'Review customers');
    ctx.hasReview = !!c.reviewItems;
    ctx.canApply = !!canApply;
    ctx.previewData = data;

    $('#tallyPreviewModal').modal('show');
  }

  function applyChanges() {
    var $btn = $('#tallyApplyChanges');
    if (!ctx.canApply) {
      $('#tallyPreviewModal').modal('hide');
      openReview(ctx.previewData.reviewItems, ctx.previewData.roster);
      return;
    }
    var orig = $btn.html();
    $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Applying…');
    postJson(syncUrl(ctx.userId), Object.assign({}, ctx.period, { apply: 1 })).then(function (a) {
      $btn.prop('disabled', false).html(orig);
      if (a.status !== 'success') { swalErr(a.data || 'Could not apply.'); return; }
      $('#tallyPreviewModal').modal('hide');
      var ac = a.data.counts;
      var msg = ac.blankFills + ' filled, ' + ac.overwrites + ' corrected, ' + ac.newCustomers + ' created, ' + ac.renames + ' renamed.';
      if (ac.reviewItems) {
        openReview(a.data.reviewItems, a.data.roster);
        swalOk(msg + ' Now resolve ' + ac.reviewItems + ' that need a decision.');
      } else {
        swalOk(msg);
      }
      ctx.onDone(a.data);
    }).catch(function () { $btn.prop('disabled', false).html(orig); swalErr('Could not reach the server.'); });
  }

  function monthLine(months) {
    var parts = [];
    Object.keys(months).forEach(function (k) { if (Number(months[k])) parts.push(fmtMonth(k) + ' ₹' + Number(months[k]).toFixed(2)); });
    return parts.length ? parts.join(' · ') : '—';
  }

  function targetPickerHtml(candidates, roster) {
    var html = '<select class="form-select form-select-sm review-target" style="width:100%;"><option value="">— choose existing customer —</option>';
    if (candidates && candidates.length) {
      html += '<optgroup label="Suggested matches">';
      candidates.forEach(function (c) { html += '<option value="' + c.pipeline_id + '">' + esc(c.party_name + ' — ' + c.score + '% (₹' + c.currentTotal + 'L)') + '</option>'; });
      html += '</optgroup>';
    }
    html += '<optgroup label="All customers">';
    (roster || []).forEach(function (p) { html += '<option value="' + p.pipeline_id + '">' + esc(p.party_name + ' (₹' + p.total + 'L)') + '</option>'; });
    return html + '</optgroup></select>';
  }

  function openReview(items, roster) {
    $('#tallyReviewName').text(ctx.name);
    $('#tallyReviewPeriod').text(ctx.previewData ? ctx.previewData.period : '');
    var $tbody = $('#tallyReviewTable tbody').empty();
    (items || []).forEach(function (it) {
      var hasCand = it.candidates && it.candidates.length;
      var candNote = hasCand
        ? '<div class="small text-muted">closest: ' + it.candidates.map(function (c) { return esc(c.party_name) + ' (' + c.score + '%)'; }).join(', ') + '</div>'
        : '<div class="small text-muted">no similar existing customer</div>';
      var $tr = $('<tr>').attr('data-setu-customer', it.setuCustomer);
      $tr.append('<td><div class="fw-semibold">' + esc(it.setuCustomer) + '</div><div class="small text-primary">' + monthLine(it.months) + '</div>' + candNote + '</td>');
      $tr.append('<td class="text-end fw-semibold">₹' + Number(it.total).toFixed(2) + 'L</td>');
      $tr.append('<td><select class="form-select form-select-sm review-action">'
        + '<option value="update"' + (hasCand ? ' selected' : '') + '>Update existing</option>'
        + '<option value="create"' + (hasCand ? '' : ' selected') + '>Create new</option>'
        + '<option value="skip">Skip</option></select></td>');
      $tr.append('<td>' + targetPickerHtml(it.candidates, roster) + '</td>');
      $tbody.append($tr);
    });
    $('#tallyReviewTable .review-target').select2({ dropdownParent: $('#tallyReviewModal'), placeholder: '— choose existing customer —', width: '100%' });
    $('#tallyReviewTable tbody tr').each(function () { targetState($(this)); });
    $('#tallyReviewModal').modal('show');
  }

  function targetState($tr) {
    var isUpdate = $tr.find('.review-action').val() === 'update';
    $tr.find('.review-target').prop('disabled', !isUpdate).closest('td').css('opacity', isUpdate ? '1' : '0.4');
  }

  function applyResolutions() {
    var resolutions = [], invalid = false;
    $('#tallyReviewTable tbody tr').each(function () {
      var $tr = $(this), action = $tr.find('.review-action').val();
      var res = { setuCustomer: $tr.data('setu-customer'), action: action };
      if (action === 'update') {
        var t = $tr.find('.review-target').val();
        if (!t) { invalid = true; $tr.find('.select2-selection').css('border-color', '#dc2626'); return; }
        res.targetPipelineId = parseInt(t, 10);
      }
      resolutions.push(res);
    });
    if (invalid) { swalWarn('Some rows are set to "Update existing" but have no customer chosen. Pick one, or switch them to Create new / Skip.'); return; }

    var $btn = $('#tallyApplyResolutions'), orig = $btn.html();
    $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Applying…');
    postJson(resolveUrl(ctx.userId), Object.assign({}, ctx.period, { resolutions: resolutions })).then(function (d) {
      $btn.prop('disabled', false).html(orig);
      if (d.status !== 'success') { swalErr((d.data && typeof d.data === 'string') ? d.data : 'Please review the rows.'); return; }
      $('#tallyReviewModal').modal('hide');
      var r = d.data;
      swalOk(r.updated + ' updated, ' + r.created + ' created, ' + r.skipped + ' skipped' + (r.missing ? ', ' + r.missing + ' skipped (no Tally data)' : '') + '.');
      ctx.onDone(d.data);
    }).catch(function () { $btn.prop('disabled', false).html(orig); swalErr('Could not reach the server.'); });
  }

  function swalOk(m) { if (window.showSuccessSwal) window.showSuccessSwal(m); else if (window.Swal) Swal.fire({ icon: 'success', title: 'Done', text: m }); }
  function swalErr(m) { if (window.showErrorSwal) window.showErrorSwal(m); else if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: m }); else alert(m); }
  function swalWarn(m) { if (window.Swal) Swal.fire({ icon: 'warning', title: 'Check the rows', text: m }); else alert(m); }

  $(function () {
    $('#tallyApplyChanges').on('click', applyChanges);
    $('#tallyApplyResolutions').on('click', applyResolutions);
    $(document).on('change', '#tallyReviewTable .review-action', function () { targetState($(this).closest('tr')); });
  });

  return { run: run };
})();
