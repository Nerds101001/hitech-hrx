'use strict';

// Customer <-> Tally verification badges on the Sales Report grid.
// Click a badge -> preview (suggested match + recent sales, or a search box)
// -> confirm or pick a different Tally party -> badge updates in place.
// Mirrors window.CustomerSetuMapping exactly, pointed at the Tally routes.
window.CustomerTallyMapping = (function () {
  function csrf() { return $('meta[name="csrf-token"]').attr('content'); }
  function esc(t) { return $('<div>').text(t == null ? '' : t).html(); }
  function previewUrl(id) { return baseUrl + 'sales-visits/sales-report/customer/' + id + '/tally-preview'; }
  function searchUrl() { return baseUrl + 'sales-visits/sales-report/tally-customers/search'; }
  function mapUrl(id) { return baseUrl + 'sales-visits/sales-report/customer/' + id + '/map-tally'; }
  function bulkConfirmUrl() { return baseUrl + 'sales-visits/sales-report/customers/bulk-confirm-tally'; }
  function syncMonthPreviewUrl(id) { return baseUrl + 'sales-visits/sales-report/customer/' + id + '/tally-sync-current-month-preview'; }
  function syncMonthUrl(id) { return baseUrl + 'sales-visits/sales-report/customer/' + id + '/tally-sync-current-month'; }

  function getJson(url, params) {
    return fetch(url + '?' + new URLSearchParams(params || {}), { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); });
  }
  function postJson(url, body) {
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify(body || {}) }).then(function (r) { return r.json(); });
  }

  var ctx = { customerId: null, locked: false };
  var searchTimer = null;

  function monthsTable(recentMonths) {
    var keys = Object.keys(recentMonths || {}).sort();
    if (!keys.length) return '<span class="text-muted small">No recent Tally sales on file.</span>';
    var html = '<table class="table table-sm mb-0"><thead><tr>';
    keys.forEach(function (k) { html += '<th class="small">' + esc(k) + '</th>'; });
    html += '</tr></thead><tbody><tr>';
    keys.forEach(function (k) { html += '<td>&#8377;' + Number(recentMonths[k]).toLocaleString('en-IN') + 'L</td>'; });
    html += '</tr></tbody></table>';
    return html;
  }

  function candidateCard(c, actionHtml) {
    return '<div class="d-flex justify-content-between align-items-start mb-2">'
      + '<div><div class="fw-semibold">' + esc(c.name) + '</div>'
      + '<div class="small text-muted">Recent total: &#8377;' + Number(c.recent_total).toLocaleString('en-IN') + 'L'
      + (c.score != null ? ' &middot; ' + c.score + '% match' : '') + '</div></div>'
      + actionHtml + '</div>' + monthsTable(c.recent_months);
  }

  function renderCandidates($container, candidates, mapLabel, locked) {
    if (!candidates || !candidates.length) {
      $container.html('<p class="text-muted small mb-0">No close matches found — search below.</p>');
      return;
    }
    var html = '';
    candidates.forEach(function (c) {
      var action = c.is_current
        ? '<span class="badge bg-label-success">Currently verified</span>'
        : locked
          ? ''
          : '<button type="button" class="btn btn-sm btn-outline-primary js-ctm-confirm" data-tally-norm="' + esc(c.normalized_name) + '">'
            + '<i class="bx bx-link"></i> ' + mapLabel + '</button>';
      html += '<div class="border rounded-3 p-2 mb-2' + (c.is_current ? ' border-success' : '') + '">' + candidateCard(c, action) + '</div>';
    });
    $container.html(html);
  }

  function open(customerId, customerName) {
    ctx.customerId = customerId;
    ctx.locked = false;
    $('#ctmCustomerName').text(customerName || '');
    $('#ctmSuggestedBlock').addClass('d-none');
    $('#ctmSuggestedCard').empty();
    $('#ctmSearchResults').empty();
    $('#ctmSearchInput').val('');
    $('#ctmLockedNotice').addClass('d-none');
    $('#ctmSearchSection').removeClass('d-none');
    $('#ctmStatusLine').html('<i class="bx bx-loader-alt bx-spin"></i> Loading...');
    $('#customerTallyModal').modal('show');

    getJson(previewUrl(customerId)).then(function (d) {
      if (d.status !== 'success') { $('#ctmStatusLine').text('Could not load this customer.'); return; }
      var cust = d.data.customer;
      ctx.locked = !!cust.locked;
      var statusLabel = {
        none: 'Not Found in Tally', similar: 'Similar match found — review below',
        suggested: 'Match Found — review and confirm below', verified: 'Verified',
      }[cust.status] || cust.status;
      var line = statusLabel;
      if (cust.status === 'verified') {
        line += ' by ' + esc(cust.verified_by || 'someone') + ' on ' + esc(cust.verified_at || '');
      }
      $('#ctmStatusLine').text(line);
      $('#ctmSyncMonthBtn').toggleClass('d-none', cust.status !== 'verified');

      $('#ctmSearchSection').toggleClass('d-none', ctx.locked);
      $('#ctmLockedNotice').toggleClass('d-none', !ctx.locked);

      $('#ctmSuggestedBlock').removeClass('d-none');
      $('#ctmSuggestedTitle').text(cust.status === 'verified' ? 'Nearest matches — currently verified one is marked' : 'Nearest matches');
      renderCandidates($('#ctmSuggestedCard'), d.data.candidates, 'Map Customer', ctx.locked);
    }).catch(function () { $('#ctmStatusLine').text('Could not reach the server.'); });
  }

  function syncCurrentMonth() {
    if (!ctx.customerId) return;
    getJson(syncMonthPreviewUrl(ctx.customerId)).then(function (d) {
      if (d.status !== 'success') { swalErr((d.data && d.data.message) || 'Could not load a preview.'); return; }
      var p = d.data;
      if (p.tally_amount == null || p.tally_amount <= 0) {
        swalErr('Tally has no sales figure for ' + p.month_label + ' for this customer.');
        return;
      }
      var rowsHtml = p.rows.map(function (r) {
        var changed = Number(r.current).toFixed(2) !== Number(p.tally_amount).toFixed(2);
        return '<tr' + (changed ? ' style="color:#b91c1c;font-weight:600;"' : '') + '>'
          + '<td class="text-start">' + esc(r.party_name) + (r.salesperson ? ' <span class="text-muted small">(' + esc(r.salesperson) + ')</span>' : '') + '</td>'
          + '<td>&#8377;' + Number(r.current).toLocaleString('en-IN') + 'L</td>'
          + '<td>&rarr;</td>'
          + '<td>&#8377;' + Number(p.tally_amount).toLocaleString('en-IN') + 'L</td>'
          + '</tr>';
      }).join('');
      var html = '<div class="text-start small mb-2">' + p.month_label + ' — Tally says &#8377;' + Number(p.tally_amount).toLocaleString('en-IN') + 'L</div>'
        + '<table class="table table-sm"><thead><tr><th class="text-start">Row</th><th>Current</th><th></th><th>After sync</th></tr></thead><tbody>' + rowsHtml + '</tbody></table>';

      if (!window.Swal) {
        if (confirm('Apply Tally\'s ' + p.month_label + ' figure (' + p.tally_amount + 'L) to ' + p.rows.length + ' row(s)?')) applySyncCurrentMonth();
        return;
      }
      Swal.fire({
        title: 'Sync ' + p.month_label + '?', html: html, icon: 'question',
        showCancelButton: true, confirmButtonText: 'Yes, sync', width: 560,
      }).then(function (r) { if (r.isConfirmed) applySyncCurrentMonth(); });
    }).catch(function () { swalErr('Could not reach the server.'); });
  }

  function applySyncCurrentMonth() {
    postJson(syncMonthUrl(ctx.customerId), {}).then(function (d) {
      if (d.status !== 'success') { swalErr((d.data && d.data.message) || 'Could not sync.'); return; }
      swalOk(d.data.message);
    }).catch(function () { swalErr('Could not reach the server.'); });
  }

  function runSearch(term) {
    if (term.length < 2) { $('#ctmSearchResults').empty(); return; }
    $('#ctmSearchResults').html('<div class="text-muted small"><i class="bx bx-loader-alt bx-spin"></i> Searching...</div>');
    getJson(searchUrl(), { q: term }).then(function (d) {
      if (d.status !== 'success' || !d.data.results.length) {
        $('#ctmSearchResults').html('<div class="text-muted small">No Tally parties match that search.</div>');
        return;
      }
      renderCandidates($('#ctmSearchResults'), d.data.results, 'Map Customer', ctx.locked);
    });
  }

  function confirmMatch(normalizedName) {
    if (!ctx.customerId) return;
    postJson(mapUrl(ctx.customerId), { tally_normalized_name: normalizedName }).then(function (d) {
      if (d.status !== 'success') { swalErr((d.data && d.data.message) || d.data || 'Could not save this mapping.'); return; }
      swalOk(d.data.message || 'Verified.');
      updateBadgeInGrid(ctx.customerId, 'verified');
      $('#customerTallyModal').modal('hide');
    }).catch(function () { swalErr('Could not reach the server.'); });
  }

  function updateBadgeInGrid(customerId, status) {
    var $badge = $('.js-tally-badge[data-customer-id="' + customerId + '"]');
    if (!$badge.length) return;
    $badge.removeClass('bg-label-danger bg-label-warning bg-label-info bg-label-success')
      .addClass('bg-label-success')
      .html('<i class="bx bx-check-shield"></i> Verified');
  }

  function bulkConfirm() {
    if (!window.Swal) { if (!confirm('Confirm every exact Tally match now?')) return; return doBulkConfirm(); }
    Swal.fire({
      title: 'Confirm all exact matches?',
      text: 'Every customer already flagged as an exact Tally match will be marked Verified.',
      icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, confirm all',
    }).then(function (r) { if (r.isConfirmed) doBulkConfirm(); });
  }

  function doBulkConfirm() {
    postJson(bulkConfirmUrl(), {}).then(function (d) {
      if (d.status !== 'success') { swalErr((d.data && d.data.message) || 'Could not confirm.'); return; }
      swalOk(d.data.message);
      $('.js-tally-badge.bg-label-warning').each(function () {
        $(this).removeClass('bg-label-warning').addClass('bg-label-success').html('<i class="bx bx-check-shield"></i> Verified');
      });
    }).catch(function () { swalErr('Could not reach the server.'); });
  }

  function swalOk(m) { if (window.showSuccessSwal) window.showSuccessSwal(m); else if (window.Swal) Swal.fire({ icon: 'success', title: 'Done', text: m }); }
  function swalErr(m) { if (window.showErrorSwal) window.showErrorSwal(m); else if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: m }); else alert(m); }

  $(function () {
    $(document).on('click', '.js-tally-badge', function () {
      var $row = $(this).closest('tr');
      var partyName = $row.find('input[data-field="party_name"]').val() || '';
      open($(this).data('customer-id'), partyName);
    });
    $(document).on('click', '.js-ctm-confirm', function () { confirmMatch($(this).data('tally-norm')); });
    $(document).on('click', '#ctmSyncMonthBtn', syncCurrentMonth);
    $('#ctmSearchInput').on('input', function () {
      var term = $(this).val().trim();
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function () { runSearch(term); }, 350);
    });
    $(document).on('click', '#btnBulkConfirmTally', bulkConfirm);
  });

  return { open: open, bulkConfirm: bulkConfirm };
})();
