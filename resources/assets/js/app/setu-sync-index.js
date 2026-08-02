'use strict';

// Setu Sales Sync centre — thin wiring over the shared window.SetuSync driver
// (setu-review-common.js), which owns the preview + review modals.
$(function () {
  // Plain client-side filter — a few dozen rows at most.
  $('#setuSyncSearchInput').on('keyup', function () {
    var term = $(this).val().toLowerCase();
    $('#setuSyncTable tbody tr[data-search]').each(function () {
      $(this).toggle($(this).data('search').toString().indexOf(term) !== -1);
    });
  });

  $(document).on('click', '.btn-sync-row', function () {
    var $btn = $(this);
    var userId = $btn.data('user-id');
    var $result = $btn.closest('tr').find('.sync-result');
    var original = $btn.html();

    $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Checking…');
    $result.removeClass('text-danger text-success').addClass('text-muted').text('Checking…');

    window.SetuSync.run(userId, {
      onDone: function (data) {
        var c = data.counts;
        $result.removeClass('text-muted text-danger').addClass('text-success').text(
          (data.applied
            ? (c.blankFills + ' filled, ' + c.overwrites + ' corrected, ' + c.newCustomers + ' created, ' + c.renames + ' renamed')
            : (data.updated + ' updated, ' + data.created + ' created')) + '.'
        );
      }
    }).then(function () {
      $btn.prop('disabled', false).html(original);
      $result.removeClass('text-muted').text('Preview open…');
    }).catch(function (e) {
      $btn.prop('disabled', false).html(original);
      $result.removeClass('text-muted text-success').addClass('text-danger').text((e && e.message) || 'Sync failed.');
    });
  });
});
