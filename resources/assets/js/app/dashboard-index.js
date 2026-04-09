'use strict';

$(function () {

  let cardColor, headingColor, labelColor, borderColor, legendColor;

  if (isDarkStyle) {
    cardColor = config.colors_dark.cardColor;
    headingColor = config.colors_dark.headingColor;
    labelColor = config.colors_dark.textMuted;
    legendColor = config.colors_dark.bodyColor;
    borderColor = config.colors_dark.borderColor;
  } else {
    cardColor = config.colors.cardColor;
    headingColor = config.colors.headingColor;
    labelColor = config.colors.textMuted;
    legendColor = config.colors.bodyColor;
    borderColor = config.colors.borderColor;
  }
  // Color constant
  const chartColors = {
    column: {
      series1: '#826af9',
      series2: '#d2b0ff',
      bg: '#f8d3ff'
    },
    donut: {
      series1: '#fee802',
      series2: '#F1F0F2',
      series3: '#826bf8',
      series4: '#3fd0bd'
    },
    area: {
      series1: '#29dac7',
      series2: '#60f2ca',
      series3: '#a5f8cd'
    },
    bar: {
      bg: '#1D9FF2'
    }
  };

  // ── Department Performance Chart ──────────────────────────────────
  $.ajax({
    url: window.baseUrl + 'getDepartmentPerformanceAjax',
    type: 'GET',
    success: function (response) {
      var data = response.data;
      if (!data || !data.length) {
        $('#topDepartmentsChart').html('<p class="text-muted text-center py-4">No department data available.</p>');
        return;
      }

      var present = data.reduce(function(s, d) { return s + (d.totalPresentEmployees || 0); }, 0);
      var absent  = data.reduce(function(s, d) { return s + (d.totalAbsentEmployees  || 0); }, 0);
      var onLeave = response.onLeave || 0;

      // Department bar chart
      var barOptions = {
        chart: { type: 'bar', height: 400, toolbar: { show: false } },
        title: {
          text: 'Department Attendance Overview',
          align: 'left',
          style: { fontSize: '16px', fontWeight: '600', color: '#1e293b' }
        },
        series: [
          { name: 'Present', data: data.map(function(d) { return d.totalPresentEmployees; }) },
          { name: 'Absent',  data: data.map(function(d) { return d.totalAbsentEmployees; }) }
        ],
        xaxis: {
          categories: data.map(function(d) { return d.code; }),
          labels: { style: { fontSize: '12px', fontWeight: 'bold' } }
        },
        yaxis: { title: { text: 'Number of Employees' } },
        colors: ['#008080', '#ef4444'],
        plotOptions: { bar: { horizontal: false, columnWidth: '50%', borderRadius: 6 } },
        dataLabels: { enabled: true, style: { fontSize: '10px', fontWeight: 'bold' } },
        tooltip: { shared: true, intersect: false },
        legend: { position: 'top', horizontalAlign: 'center' },
        grid: { borderColor: '#f1f5f9', strokeDashArray: 4 }
      };

      var barChartEl = document.querySelector('#topDepartmentsChart');
      if (barChartEl) {
        var barChart = new ApexCharts(barChartEl, barOptions);
        barChart.render();
      }

      // Donut chart with live data
      var donutChartEl = document.querySelector('#donutChart');
      if (donutChartEl) {
        var donutChart = new ApexCharts(donutChartEl, {
          chart: { height: 390, type: 'donut' },
          labels: ['Present', 'Absent', 'On Leave'],
          series: [present, absent, onLeave],
          colors: ['#008080', '#ef4444', '#f59e0b'],
          stroke: { show: false },
          dataLabels: {
            enabled: true,
            formatter: function (val) { return parseInt(val, 10) + '%'; }
          },
          legend: {
            show: true, position: 'bottom',
            labels: { colors: legendColor, useSeriesColors: false }
          },
          plotOptions: {
            pie: {
              donut: {
                size: '70%',
                labels: {
                  show: true,
                  name: { fontSize: '2rem', fontFamily: 'Public Sans' },
                  value: {
                    fontSize: '1.2rem', color: legendColor, fontFamily: 'Public Sans',
                    formatter: function (val) { return parseInt(val, 10); }
                  },
                  total: {
                    show: true, fontSize: '1.5rem', color: headingColor, label: 'Present',
                    formatter: function () { return present; }
                  }
                }
              }
            }
          }
        });
        donutChart.render();
      }
    },
    error: function () {
      $('#topDepartmentsChart').html('<p class="text-danger text-center py-4">Failed to load department data.</p>');
    }
  });

  // ── Recent Activities Feed ────────────────────────────────────────
  $.ajax({
    url: window.baseUrl + 'getRecentActivities',
    type: 'GET',
    success: function (response) {
      var list = document.querySelector('#activityList');
      if (!list) return;

      if (response.data && response.data.length > 0) {
        list.innerHTML = '';
        response.data.forEach(function (activity) {
          var user = activity.user || {};
          var userName = user.name || user.first_name || 'Unknown User';
          var li = document.createElement('li');
          li.className = 'list-group-item border-0 d-flex align-items-start py-3';
          li.innerHTML =
            '<div class="w-100">' +
              '<div class="d-flex justify-content-between align-items-center mb-1">' +
                '<h6 class="mb-0 fw-bold" style="font-size:0.85rem;">' +
                  (activity.type || 'Activity') + ' by <strong>' + userName + '</strong>' +
                '</h6>' +
                '<small class="text-muted">' + (activity.created_at_human || '') + '</small>' +
              '</div>' +
              '<p class="mb-0 text-muted" style="font-size:0.8rem;">' + (activity.title || '') + '</p>' +
            '</div>';
          list.appendChild(li);
        });
      } else {
        list.innerHTML = '<li class="list-group-item text-center text-muted border-0 py-4">No recent activities found.</li>';
      }
    },
    error: function () {
      var list = document.querySelector('#activityList');
      if (list) list.innerHTML = '<li class="list-group-item text-center text-muted border-0 py-4">Could not load activities.</li>';
    }
  });

});