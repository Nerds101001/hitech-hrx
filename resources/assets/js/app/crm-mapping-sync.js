'use strict';

// CRM Mapping Sync (admin overview) — thin wiring over the SAME shared
// window.CrmMappingSync driver the Sales Report page's own "Link {salesperson}
// customers to CRM" dropdown item already uses (resources/assets/js/app/
// crm-mapping-review-common.js, added 2026-08-28 - reused here rather than
// duplicated, so both entry points share one preview/review implementation).
// This page adds the piece that entry point doesn't have: a bulk view across
// every salesperson at once, instead of one at a time via a buried dropdown
// item on whichever salesperson's report happens to be open.
$(function () {
  $('#crmSyncSearchInput').on('keyup', function () {
    var term = $(this).val().toLowerCase();
    $('#crmSyncTable tbody tr[data-search]').each(function () {
      $(this).toggle($(this).data('search').toString().indexOf(term) !== -1);
    });
  });

  $(document).on('click', '.btn-crm-sync-row', function () {
    var $btn = $(this);
    var userId = $btn.data('user-id');
    var $result = $btn.closest('tr').find('.crm-sync-result');
    var original = $btn.html();

    $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Checking…');
    $result.removeClass('text-danger text-success').addClass('text-muted').text('Checking…');

    window.CrmMappingSync.run(userId, {
      onDone: function (data) {
        var c = data.counts;
        var msg = c.autoLinked
          ? c.autoLinked + ' auto-linked' + (c.reviewItems ? ', ' + c.reviewItems + ' need review' : '')
          : (c.reviewItems ? c.reviewItems + ' need review' : 'No plausible CRM match for any unlinked row — likely nothing cached to compare against yet');
        $result.removeClass('text-muted text-danger').addClass('text-success').text(msg + '.');
      }
    }).then(function () {
      $btn.prop('disabled', false).html(original);
      $result.removeClass('text-muted').text('Preview open…');
    }).catch(function (e) {
      $btn.prop('disabled', false).html(original);
      $result.removeClass('text-muted text-success').addClass('text-danger').text((e && e.message) || 'Could not check CRM mapping.');
    });
  });
});
