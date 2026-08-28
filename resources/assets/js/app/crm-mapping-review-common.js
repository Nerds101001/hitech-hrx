'use strict';

// Reconciles a salesperson's Sales Report (sales_pipelines, is_locked=1)
// against the CRM.API-synced customer registry (CrmClientMapping) they own.
// Same two-step shape as window.SetuSync (setu-review-common.js): a
// non-destructive preview, then Apply commits the auto-linked (>=99.9% name
// match) tier and opens a review queue for anything only similar.
window.CrmMappingSync = (function () {
  function csrf() { return $('meta[name="csrf-token"]').attr('content'); }
  function syncUrl(id) { return baseUrl + 'sales-visits/sales-report/sync-crm-mapping/' + id; }
  function resolveUrl(id) { return baseUrl + 'sales-visits/sales-report/resolve-crm-mapping/' + id; }
  function esc(t) { return $('<div>').text(t == null ? '' : t).html(); }
  function postJson(url, body) {
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify(body) }).then(function (r) { return r.json(); });
  }

  var ctx = {}; // { salespersonId, name, onDone, previewData }

  // ── Entry point: dry preview (apply=0), then open the preview modal ──────
  function run(salespersonId, opts) {
    opts = opts || {};
    ctx = { salespersonId: salespersonId, onDone: opts.onDone || function () {} };
    return postJson(syncUrl(salespersonId), {}).then(function (d) {
      if (d.status !== 'success') { throw new Error((d.data && d.data.message) || d.data || 'Could not check CRM mapping.'); }
      ctx.name = d.data.salesperson;
      ctx.previewData = d.data;
      renderPreview(d.data);
      return d.data;
    });
  }

  function chip(cls, n, label) { return '<span class="badge ' + cls + ' rounded-pill px-3 py-2">' + n + ' ' + label + '</span>'; }

  function renderPreview(data) {
    var c = data.counts;
    $('#crmMappingPreviewName').text(data.salesperson);

    var chips = '';
    if (c.autoLinked) chips += chip('bg-label-success', c.autoLinked, 'exact match' + (c.autoLinked === 1 ? '' : 'es'));
    if (c.reviewItems) chips += chip('bg-label-warning', c.reviewItems, 'need' + (c.reviewItems === 1 ? 's' : '') + ' your review');
    var noMatch = c.unlinkedTotal - c.autoLinked - c.reviewItems;
    if (noMatch > 0) chips += chip('bg-label-secondary', noMatch, 'no CRM match yet');
    if (!chips) chips = '<span class="text-muted small">Every customer in this Sales Report is already linked to a CRM account.</span>';
    $('#crmMappingPreviewSummary').html(chips);

    var listHtml = '';
    if (data.autoLinked && data.autoLinked.length) {
      listHtml += '<h6 class="fw-bold mb-2 mt-1">Will be linked automatically (exact name match)</h6>';
      listHtml += '<ul class="list-unstyled small mb-3">';
      data.autoLinked.forEach(function (r) {
        listHtml += '<li class="mb-1"><i class="bx bx-link text-success me-1"></i>' + esc(r.party_name) + ' &rarr; <span class="fw-semibold">' + esc(r.crm_company_name) + '</span></li>';
      });
      listHtml += '</ul>';
    }
    if (data.reviewItems && data.reviewItems.length) {
      listHtml += '<div class="alert alert-warning py-2 px-3 small mb-0">'
        + '<b>' + data.reviewItems.length + '</b> customer' + (data.reviewItems.length === 1 ? '' : 's') + ' look similar to an existing CRM account but aren\'t an exact match — you\'ll decide those'
        + (data.autoLinked.length ? ' right after applying.' : ' next.') + '</div>';
    }
    if (!listHtml) {
      listHtml = '<p class="text-muted small mb-0">Nothing new to link right now.</p>';
    }
    $('#crmMappingPreviewSections').html(listHtml);

    var canApply = !!c.autoLinked;
    $('#crmApplyMapping .label').text(canApply ? 'Apply Changes' : (c.reviewItems ? 'Review customers' : 'Close'));
    ctx.canApply = canApply;
    ctx.hasReview = !!c.reviewItems;

    $('#crmMappingPreviewModal').modal('show');
  }

  // Apply Changes → commit the auto-linked tier, then open review if needed.
  function applyChanges() {
    var $btn = $('#crmApplyMapping');
    if (!ctx.canApply) {
      $('#crmMappingPreviewModal').modal('hide');
      if (ctx.hasReview) { openReview(ctx.previewData.reviewItems); }
      return;
    }
    var orig = $btn.html();
    $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Applying…');
    postJson(syncUrl(ctx.salespersonId), { apply: 1 }).then(function (a) {
      $btn.prop('disabled', false).html(orig);
      if (a.status !== 'success') { swalErr((a.data && a.data.message) || a.data || 'Could not apply.'); return; }
      $('#crmMappingPreviewModal').modal('hide');
      var ac = a.data.counts;
      var msg = ac.autoLinked + ' linked automatically.';
      if (ac.reviewItems) {
        openReview(a.data.reviewItems);
        swalOk(msg + ' Now resolve ' + ac.reviewItems + ' that need a decision.');
      } else {
        swalOk(msg);
      }
      ctx.onDone(a.data);
    }).catch(function () { $btn.prop('disabled', false).html(orig); swalErr('Could not reach the server.'); });
  }

  // ── Review modal ─────────────────────────────────────────────────────────
  function openReview(items) {
    $('#crmReviewName').text(ctx.name);
    var $tbody = $('#crmReviewTable tbody').empty();
    (items || []).forEach(function (it) {
      var hasCand = it.candidates && it.candidates.length;
      var $tr = $('<tr>').attr('data-pipeline-id', it.pipeline_id);
      $tr.append('<td><span class="fw-semibold">' + esc(it.party_name) + '</span></td>');
      $tr.append('<td><select class="form-select form-select-sm review-action">'
        + '<option value="link"' + (hasCand ? ' selected' : '') + '>Link to CRM account</option>'
        + '<option value="skip"' + (hasCand ? '' : ' selected') + '>Skip — not the same customer</option></select></td>');
      $tr.append('<td>' + targetPickerHtml(it.candidates) + '</td>');
      $tbody.append($tr);
    });
    $('#crmReviewTable .review-target').select2({ dropdownParent: $('#crmReviewModal'), placeholder: '— choose CRM account —', width: '100%' });
    $('#crmReviewTable tbody tr').each(function () { targetState($(this)); });
    $('#crmReviewModal').modal('show');
  }

  function targetPickerHtml(candidates) {
    var html = '<select class="form-select form-select-sm review-target" style="width:100%;"><option value="">— choose CRM account —</option>';
    (candidates || []).forEach(function (c) {
      html += '<option value="' + c.mapping_id + '">' + esc(c.crm_company_name + ' — ' + c.score + '% match') + '</option>';
    });
    return html + '</select>';
  }

  function targetState($tr) {
    var isLink = $tr.find('.review-action').val() === 'link';
    $tr.find('.review-target').prop('disabled', !isLink).closest('td').css('opacity', isLink ? '1' : '0.4');
  }

  function applyResolutions() {
    var resolutions = [], invalid = false;
    $('#crmReviewTable tbody tr').each(function () {
      var $tr = $(this), action = $tr.find('.review-action').val();
      var res = { pipeline_id: parseInt($tr.data('pipeline-id'), 10), action: action };
      if (action === 'link') {
        var t = $tr.find('.review-target').val();
        if (!t) { invalid = true; $tr.find('.select2-selection').css('border-color', '#dc2626'); return; }
        res.mapping_id = parseInt(t, 10);
      }
      resolutions.push(res);
    });
    if (invalid) { swalWarn('Some rows are set to "Link to CRM account" but have no account chosen. Pick one, or switch them to Skip.'); return; }

    var $btn = $('#crmApplyResolutions'), orig = $btn.html();
    $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Applying…');
    postJson(resolveUrl(ctx.salespersonId), { resolutions: resolutions }).then(function (d) {
      $btn.prop('disabled', false).html(orig);
      if (d.status !== 'success') { swalErr((d.data && typeof d.data === 'string') ? d.data : 'Please review the rows.'); return; }
      $('#crmReviewModal').modal('hide');
      swalOk(d.data.linked + ' linked, ' + d.data.skipped + ' skipped.');
      ctx.onDone(d.data);
    }).catch(function () { $btn.prop('disabled', false).html(orig); swalErr('Could not reach the server.'); });
  }

  function swalOk(m) { if (window.showSuccessSwal) window.showSuccessSwal(m); else if (window.Swal) Swal.fire({ icon: 'success', title: 'Done', text: m }); }
  function swalErr(m) { if (window.showErrorSwal) window.showErrorSwal(m); else if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: m }); else alert(m); }
  function swalWarn(m) { if (window.Swal) Swal.fire({ icon: 'warning', title: 'Check the rows', text: m }); else alert(m); }

  $(function () {
    $('#crmApplyMapping').on('click', applyChanges);
    $('#crmApplyResolutions').on('click', applyResolutions);
    $(document).on('change', '#crmReviewTable .review-action', function () { targetState($(this).closest('tr')); });
  });

  return { run: run };
})();
