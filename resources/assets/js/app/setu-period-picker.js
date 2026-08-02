'use strict';

// Shared Setu sync period picker (fiscal year + whole-year/quarter/custom
// months). Included once per page via the setu-period-picker partial;
// exposes window.getSetuPeriod() for whichever page's own JS builds the
// sync POST body. All quarter/whole-year -> month-bounds math stays
// server-side (SetuReportService::resolvePeriod()/quarterMonthRange()) —
// this file only collects the raw select values, it never computes a range.
(function () {
  var MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

  function pad2(n) {
    return (n < 10 ? '0' : '') + n;
  }

  // "FY-2025-2026" -> [{value:"2025-04",label:"Apr 2025"}, ..., {value:"2026-03",label:"Mar 2026"}]
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
    var months = fyMonths($('#setu_fy').val());
    var $from = $('#setu_from_month'), $to = $('#setu_to_month');
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
    var isCustom = $('#setu_period').val() === 'custom';
    $('#setu_custom_months').css('display', isCustom ? 'inline-flex' : 'none');
  }

  $(function () {
    if (!$('#setu_fy').length) return; // partial not included on this page
    populateMonthSelects();
    toggleCustomRange();
    $('#setu_fy').on('change', populateMonthSelects);
    $('#setu_period').on('change', toggleCustomRange);
  });

  window.getSetuPeriod = function () {
    var period = $('#setu_period').val() || 'whole';
    return {
      fy: $('#setu_fy').val(),
      period: period,
      from: period === 'custom' ? $('#setu_from_month').val() : null,
      to: period === 'custom' ? $('#setu_to_month').val() : null
    };
  };
})();
