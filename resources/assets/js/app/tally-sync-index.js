'use strict';

// Tally Sales Sync centre — thin wiring over the shared window.TallySync
// driver (tally-review-common.js), mirroring setu-sync-index.js.
$(function () {
  $('#tallySyncSearchInput').on('keyup', function () {
    var term = $(this).val().toLowerCase();
    $('#tallySyncTable tbody tr[data-search]').each(function () {
      $(this).toggle($(this).data('search').toString().indexOf(term) !== -1);
    });
  });

  $(document).on('click', '.btn-recheck-row', function () {
    var $btn = $(this);
    var userId = $btn.data('user-id');
    var $result = $btn.closest('tr').find('.sync-result');
    var original = $btn.html();

    $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Checking…');
    $result.removeClass('text-danger text-success').addClass('text-muted').text('Checking…');

    var period = window.getTallyPeriod ? window.getTallyPeriod() : {};
    $.get(baseUrl + 'sales-visits/sales-report/recheck-tally/' + userId, period)
      .done(function (d) {
        if (d.status !== 'success') {
          $result.removeClass('text-muted text-success').addClass('text-danger').text((d.data && d.data) || 'Recheck failed.');
          return;
        }
        var r = d.data;
        var months = Object.keys(r.monthTotalsLakhs || {});
        var monthLine = months.length
          ? months.map(function (k) { var p = k.split('-'); var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']; return MONTHS[parseInt(p[1], 10) - 1] + ' ₹' + Number(r.monthTotalsLakhs[k]).toFixed(2) + 'L'; }).join(' · ')
          : 'no sales this period';
        $result.removeClass('text-muted text-danger').addClass('text-success').html(
          '<b>₹' + Number(r.grandTotalLakhs).toFixed(2) + 'L</b> · ' + r.customersBilled + ' customers billed'
          + '<div class="text-muted" style="font-size:.72rem;">' + monthLine + ' <span class="fst-italic">(as of ' + r.checkedAt + ')</span></div>'
        );
      })
      .fail(function () {
        $result.removeClass('text-muted text-success').addClass('text-danger').text('Could not reach the server.');
      })
      .always(function () {
        $btn.prop('disabled', false).html(original);
      });
  });

  $(document).on('click', '.btn-sync-row', function () {
    var $btn = $(this);
    var userId = $btn.data('user-id');
    var $result = $btn.closest('tr').find('.sync-result');
    var original = $btn.html();

    $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Checking…');
    $result.removeClass('text-danger text-success').addClass('text-muted').text('Checking…');

    window.TallySync.run(userId, {
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
