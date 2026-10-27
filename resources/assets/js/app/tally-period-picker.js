'use strict';

// Tally sync period picker — mirrors setu-period-picker.js exactly, pointed
// at the tally_* element ids; exposes window.getTallyPeriod().
(function () {
  var MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

  function pad2(n) {
    return (n < 10 ? '0' : '') + n;
  }

  function fyMonths(fy) {
    var m = /^FY-(\d{4})-(\d{4})$/.exec(fy || '');
    if (!m) return [];
    var y1 = parseInt(m[1], 10), y2 = parseInt(m[2], 10);
    var months = [];
    var mo;
    for (mo = 4; mo <= 12; mo++) {
      months.push({ value: y1 + '-' + pad2(mo), label: MONTH_NAMES[mo - 1] + ' ' + y1 });
    }
    for (mo = 1; mo <= 3; mo++) {
      months.push({ value: y2 + '-' + pad2(mo), label: MONTH_NAMES[mo - 1] + ' ' + y2 });
    }
    return months;
  }

  function populateMonthSelects() {
    var months = fyMonths($('#tally_fy').val());
    var $from = $('#tally_from_month'), $to = $('#tally_to_month');
    var prevFrom = $from.val(), prevTo = $to.val();
    var values = [];
    $from.empty();
    $to.empty();
    months.forEach(function (m) {
      values.push(m.value);
      $from.append($('<option>').val(m.value).text(m.label));
      $to.append($('<option>').val(m.value).text(m.label));
    });
    $from.val(values.indexOf(prevFrom) !== -1 ? prevFrom : values[0]);
    $to.val(values.indexOf(prevTo) !== -1 ? prevTo : values[values.length - 1]);
  }

  function toggleCustomRange() {
    var isCustom = $('#tally_period').val() === 'custom';
    $('#tally_custom_months').css('display', isCustom ? 'inline-flex' : 'none');
  }

  $(function () {
    if (!$('#tally_fy').length) return;
    populateMonthSelects();
    toggleCustomRange();
    $('#tally_fy').on('change', populateMonthSelects);
    $('#tally_period').on('change', toggleCustomRange);
  });

  window.getTallyPeriod = function () {
    var period = $('#tally_period').val() || 'whole';
    return {
      fy: $('#tally_fy').val(),
      period: period,
      from: period === 'custom' ? $('#tally_from_month').val() : null,
      to: period === 'custom' ? $('#tally_to_month').val() : null
    };
  };
})();
