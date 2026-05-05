/* Attendance Index */

'use strict';

let registryModal = null;
let summaryModal = null;

$(function () {
  console.log('Attendance Index');

  var dataTable = $('#attendanceTable').DataTable({
    processing: true,
    serverSide: true,
    dom: 'rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>', 
    ajax: {
      url: 'attendance/indexAjax',
      data: function data(d) {
        d.userId = $('#userId').val();
        d.shiftId = $('#shiftId').val();
        d.teamId = $('#teamId').val();
        d.siteId = $('#siteId').val();
        d.searchTerm = $('#customSearchInput').val();

        const quickPeriod = $('#quickPeriod').val();
        if (quickPeriod && quickPeriod !== 'custom') {
            let start = moment();
            let end = moment();

            if (quickPeriod === 'yesterday') {
                start = moment().subtract(1, 'days');
                end = moment().subtract(1, 'days');
            } else if (quickPeriod === '7days') {
                start = moment().subtract(7, 'days');
                end = moment();
            } else if (quickPeriod === 'last_month') {
                start = moment().subtract(1, 'months').startOf('month');
                end = moment().subtract(1, 'months').endOf('month');
            } else if (quickPeriod === 'today') {
                start = moment();
                end = moment();
            }

            d.startDate = start.format('YYYY-MM-DD');
            d.endDate = end.format('YYYY-MM-DD');
        } else {
            d.date = $('#date').val();
        }
      }
    },
    columns: [
      { data: 'user', name: 'user' },
      { data: 'date', name: 'date' },
      { data: 'shift', name: 'shift' },
      { data: 'check_in_time', name: 'check_in_time' },
      { data: 'check_out_time', name: 'check_out_time' },
      { data: 'working_hours', name: 'working_hours' },
      { data: 'status', name: 'status' },
      { data: 'actions', name: 'actions' }
    ],
    columnDefs: [
      { targets: [3, 4, 5, 6, 7], className: 'text-start' },
      { targets: 7, className: 'status-col' }
    ],
    drawCallback: function (settings) {
      const json = dataTable.ajax.json();
      if (json && json.stats) {
        // Determine selected period text for card titles
        let periodPrefix = 'Today\'s';
        const qp = $('#quickPeriod').val();
        if (qp === 'yesterday') periodPrefix = 'Yesterday\'s';
        else if (qp === '7days') periodPrefix = '7 Days';
        else if (qp === 'last_month') periodPrefix = 'Last Month\'s';
        else if (qp === 'custom') periodPrefix = 'Filtered';

        // Update titles
        if ($('#statPresentCount-title').length) $('#statPresentCount-title').text(`${periodPrefix} Present`);
        if ($('#statAbsentCount-title').length) $('#statAbsentCount-title').text(`${periodPrefix} Absent`);
        if ($('#statLeaveCount-title').length) $('#statLeaveCount-title').text(`${periodPrefix} On Leave`);
        if ($('#statLateCount-title').length) $('#statLateCount-title').text(`${periodPrefix} Late`);

        if ($('#statPresentCount').length) {
          // Update primary value with brief animation
          $('#statPresentCount').prop('Counter', 0).animate({
              Counter: json.stats.present
          }, {
              duration: 500,
              easing: 'swing',
              step: function (now) { $(this).text(Math.ceil(now)); }
          });
        }
        
        if ($('#statAbsentCount').length) $('#statAbsentCount').text(json.stats.absent);
        if ($('#statLeaveCount').length) $('#statLeaveCount').text(json.stats.leave);
        if ($('#statLateCount').length) $('#statLateCount').text(json.stats.late);

        // Optional: Update trend percentage if needed
        const trendEl = $('#statPresentCount').closest('.stat-card-body').find('.text-success');
        if (trendEl.length) {
          trendEl.html(`+${json.stats.presentPercentage}%`);
        }
      }
    }
  });

  $('#userId, #shiftId, #teamId, #siteId').select2();

  const reloadRegistryIfActive = () => {
    if ($('#registry-view-tab').hasClass('active')) {
      loadMonthlyRegistry();
    }
  };

  $('#quickPeriod').on('change', function () {
    const val = $(this).val();
    if (val === 'custom') {
      $('#dateFilterWrapper').show('fast');
      $('#registryMonthWrapper').show('fast');
    } else {
      $('#dateFilterWrapper').hide('fast');
      $('#registryMonthWrapper').hide('fast');
    }
    dataTable.draw();
    reloadRegistryIfActive();
  });

  $('#userId, #date, #shiftId, #teamId, #siteId').on('change', function () {
    dataTable.draw();
    reloadRegistryIfActive();
    refreshChart(); // Real-time sync to graph
  });

  $('#registryMonth').on('change', function() {
    reloadRegistryIfActive();
  });

  $('#customSearchBtn').on('click', function () {
    dataTable.draw();
    reloadRegistryIfActive();
    refreshChart();
  });

  $('#customSearchInput').on('keyup', function (e) {
    if (e.key === 'Enter') {
      dataTable.draw();
      reloadRegistryIfActive();
      refreshChart();
    }
  });

  $('#customLengthMenu').on('change', function () {
    dataTable.page.len($(this).val()).draw();
  });

  // Chart & Sync Logic
  $('#chartTeamFilter, #chartPeriod, #chartUserFilter').select2();

  // Sync Table Filters to Chart
  $('#userId').on('change', function() {
    $('#chartUserFilter').val($(this).val()).trigger('change.select2', [true]);
  });
  $('#teamId').on('change', function() {
    $('#chartTeamFilter').val($(this).val()).trigger('change.select2', [true]);
  });

  // Sync Chart Filters to Table
  $('#chartUserFilter').on('change', function(e, isSync) {
    if (isSync) return;
    const val = $(this).val();
    if ($('#userId').val() !== val) {
        $('#userId').val(val).trigger('change');
    }
  });
  $('#chartTeamFilter').on('change', function(e, isSync) {
    if (isSync) return;
    const val = $(this).val();
    if ($('#teamId').val() !== val) {
        $('#teamId').val(val).trigger('change');
    }
  });

  $('#chartPeriod').on('change', function() {
    refreshChart();
  });

  $('#registry-view-tab').on('shown.bs.tab', function () {
    loadMonthlyRegistry();
  });

  if ($('#registry-view-tab').hasClass('active')) {
      loadMonthlyRegistry();
  }
});

window.refreshChart = function() {
  if (window.attendanceChart) {
    $.ajax({
      url: 'attendance/chart-ajax',
      method: 'GET',
      data: {
        period: $('#chartPeriod').val(),
        teamId: $('#chartTeamFilter').val(),
        userId: $('#chartUserFilter').val(),
        siteId: $('#siteId').val(),
        shiftId: $('#shiftId').val(),
        searchTerm: $('#customSearchInput').val()
      },
      success: function(resp) {
        window.attendanceChart.updateOptions({
          xaxis: { categories: resp.categories }
        });
        window.attendanceChart.updateSeries(resp.series);
      },
      error: function(err) {
        console.error('Chart Data Error:', err);
      }
    });
  }
}

window.viewLogs = function(id) {
    if (!id) return;
    $.ajax({
        url: `attendance/${id}/logs`,
        method: 'GET',
        success: function(resp) {
            if (resp.html) {
                Swal.fire({
                    title: 'System Activity Logs',
                    html: resp.html,
                    width: '800px',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false
                });
            } else {
                alert('No logs found for this record.');
            }
        },
        error: function() { alert('Failed to fetch logs.'); }
    });
}

function loadMonthlyRegistry() {
    const container = $('#registryTableContainer');
    const qp = $('#quickPeriod').val();
    
    let month = moment().month() + 1;
    let year = moment().year();
    
    // Improved month detection from qp filter
    if (qp === 'last_month') {
        const lastMonth = moment().subtract(1, 'months');
        month = lastMonth.month() + 1;
        year = lastMonth.year();
    } else if (qp === 'today' || qp === 'yesterday' || qp === '7days') {
        // If they use a quick period, we usually show current month
        // UNLESS they have explicitly changed the registryMonth picker
        const regVal = $('#registryMonth').val();
        if (regVal) {
            const m = moment(regVal, 'YYYY-MM');
            month = m.month() + 1;
            year = m.year();
        } else {
            month = moment().month() + 1;
            year = moment().year();
        }
    }

    $.ajax({
        url: 'attendance/registryAjax',
        method: 'GET',
        data: { 
            month: month, 
            year: year,
            userId: $('#userId').val(),
            shiftId: $('#shiftId').val(),
            teamId: $('#teamId').val(),
            siteId: $('#siteId').val(),
            searchTerm: $('#customSearchInput').val()
        },
        beforeSend: function() {
            container.html('<div class="text-center p-5"><div class="spinner-border text-teal" role="status"></div><p class="mt-2 text-muted fw-bold">Synchronizing with Biometric Data...</p></div>');
        },
        success: function(resp) {
            if (!resp.data || resp.data.length === 0) {
                container.html('<div class="text-center p-5"><i class="bx bx-info-circle text-muted mb-2 display-6"></i><p>No records found for current filters.</p></div>');
                return;
            }

            let html = '<table class="table table-bordered mb-0 hitech-registry-table">';
            html += '<thead class="bg-light"><tr><th class="sticky-col bg-light shadow-sm" style="left:0; z-index:10; min-width:180px;">MANAGER / EMPLOYEE</th>';
            for(let i=1; i<=resp.daysInMonth; i++) {
                html += '<th class="text-center" style="min-width:45px;"><small class="d-block text-muted text-uppercase" style="font-size:0.6rem;">' + i + '</small><small>' + moment().month(resp.month-1).format('MMM') + '</small></th>';
            }
            html += '<th class="text-center bg-light">P</th><th class="text-center bg-light">A</th><th class="text-center bg-light">L</th></tr></thead><tbody>';

            resp.data.forEach(row => {
                const safeName = row.employee.replace(/'/g, "\\'");
                const periodStr = moment().month(resp.month - 1).year(resp.year).format('MMMM YYYY');
                const summaryClick = `onclick="showEmployeeSummary('${safeName}', '${row.code}', '${row.presents}', '${row.absents}', '${row.lates}', '${row.leaves}', '${periodStr}')"`;

                html += '<tr>' +
                        '<td class="sticky-col bg-white fw-bold shadow-sm" style="left:0; z-index:5;">' + 
                          '<div class="d-flex flex-column cursor-pointer" ' + summaryClick + '>' +
                            '<span class="text-teal hover-teal" style="text-decoration: underline dotted;">' + row.employee + '</span>' +
                            '<small class="text-muted">' + row.code + '</small>' +
                          '</div>' +
                        '</td>';
                
                for(let i=1; i<=resp.daysInMonth; i++) {
                    const day = row['day_'+i];
                    // Pass 24h format if needed or original
                    const clickAttr = `onclick="showDayDetails('${day.status}', '${day.in}', '${day.out}', '${day.hours}', '${safeName}', '${i} ${moment().month(resp.month - 1).format('MMM')}', ${day.id || 'null'}, ${day.is_edited || false}, '${day.editor_name || ''}', '${(day.admin_reason || '').replace(/'/g, "\\'")}', '${day.attachment || ''}', ${day.user_id || 'null'}, '${day.full_date || ''}')"`;
                    
                    let inner = '<small class="text-muted" style="font-size:0.6rem;">--</small>';
                    if(day.status === 'Present') inner = '<i class="bx bx-check text-white"></i>';
                    if(day.status === 'Half Day') inner = '<i class="bx bx-time text-white"></i>';
                    if(day.status === 'Absent') inner = '<i class="bx bx-x text-white"></i>';
                    if(day.status === 'Holiday') inner = '<small class="fw-bold text-info" style="font-size:0.55rem;">HOL</small>';
                    if(day.status === 'OFF') inner = '<small class="fw-bold text-muted" style="font-size:0.55rem;">OFF</small>';
                    if(day.status === 'Leave') inner = `<small class="fw-bold text-white" style="font-size:0.45rem;">${day.hours}</small>`;
                    if(day.status === 'WFH') inner = '<small class="fw-bold text-white" style="font-size:0.45rem;">WFH</small>';
                    if(day.status === 'Scheduled') inner = '<i class="bx bx-calendar-event opacity-25"></i>';
                    if(day.status === 'Today') inner = '<span class="animate__animated animate__pulse animate__infinite" style="font-size:0.5rem; font-weight:900;">TODAY</span>';
                    
                    if(day.is_edited) inner += '<i class="bx bxs-edit-alt text-white opacity-75 position-absolute top-0 end-0 p-1" style="font-size:0.55rem;" title="Manual Adjustment"></i>';
                    if(day.is_short_leave) inner += '<i class="bx bxs-time-five text-white opacity-75 position-absolute top-0 start-0 p-1" style="font-size:0.55rem;" title="Short Leave Applied"></i>';

                    html += `<td class="text-center p-1" style="border: 1px solid #f1f3f5;">
                                <div class="attendance-box ${day.class} rounded-2 cursor-pointer d-flex align-items-center justify-content-center shadow-sm position-relative" 
                                     style="height:32px; min-width:32px;" 
                                     ${clickAttr}>
                                    ${inner}
                                </div>
                             </td>`;
                }
                
                html += '<td class="text-center text-success fw-bold">' + row.presents + '</td>';
                html += '<td class="text-center text-danger fw-bold">' + row.absents + '</td>';
                html += '<td class="text-center text-warning fw-bold">' + row.lates + '</td>'; 
                html += '</tr>';
            });

            html += '</tbody></table>';
            container.html(html);
        }
    });
}

window.showDayDetails = function(status, checkIn, checkOut, hours, name, date, id = null, isEdited = false, editorName = '', adminReason = '', attachment = '', userId = null, fullDate = '') {
    if (!registryModal) registryModal = new bootstrap.Modal(document.getElementById('dayDetailsModal'));
    
    currentEditId = id;
    currentEditUserId = userId;
    currentEditDate = fullDate;

    if (id || status === 'Absent' || status === 'Missing' || status === 'Today') {
        $('#editActionWrapper').removeClass('d-none');
    } else {
        $('#editActionWrapper').addClass('d-none');
    }
    
    // Audit Section
    if (isEdited) {
        $('#adjustmentAuditSection').removeClass('d-none');
        $('#auditBy').text(editorName);
        $('#auditReason').text('"' + adminReason + '"');
        
        if (attachment) {
            $('#auditAttachmentWrapper').removeClass('d-none');
            $('#auditAttachmentLink').attr('href', attachment);
        } else {
            $('#auditAttachmentWrapper').addClass('d-none');
        }
    } else {
        $('#adjustmentAuditSection').addClass('d-none');
    }

    $('#detailDate').text(date);
    $('#detailName').text(name);
    
    let badgeClass = 'teal';
    if(status === 'Present' || status === 'Full Day' || status === 'Paid Leave') badgeClass = 'teal';
    if(status === 'Late' || status === 'Half Day') badgeClass = 'orange';
    if(status === 'Absent') badgeClass = 'danger';
    if(status === 'Holiday') badgeClass = 'info';
    if(status === 'Leave' || status === 'Unpaid Leave') badgeClass = 'purple-vibrant';
    if(status === 'WFH') badgeClass = 'indigo-vibrant';
    
    $('#detailStatus').html(`<span class="badge bg-label-${badgeClass} px-4 py-2 rounded-pill fw-black text-uppercase ls-1">${status}</span>`);
    
    const isSpecial = ['Holiday', 'OFF', 'Leave'].includes(status);
    $('#detailCheckIn').text(isSpecial ? (status === 'Leave' ? checkIn : status) : checkIn);
    $('#detailCheckOut').text(isSpecial ? (status === 'Leave' ? checkIn : status) : checkOut);
    $('#detailHours').text(hours);
    
    registryModal.show();
}

// Adjustment Logic
let currentEditId = null;
let currentEditUserId = null;
let currentEditDate = null;

window.openEditFromDetails = function() {
    if (currentEditId || (currentEditUserId && currentEditDate)) {
        editRecord(currentEditId, currentEditUserId, currentEditDate);
        if (registryModal) registryModal.hide();
    }
}

window.editRecord = function(id, userId = null, date = null) {
    console.log('editRecord called', { id, userId, date });
    const editModalEl = document.getElementById('editAttendanceModal');
    if (!editModalEl) {
        console.error('editAttendanceModal element not found!');
        return;
    }
    let editModal = bootstrap.Modal.getInstance(editModalEl);
    if (!editModal) editModal = new bootstrap.Modal(editModalEl);

    if (!id) {
        // Create mode
        $('#editAttendanceId').val('');
        $('#editEmpName').text('New Adjustment');
        $('#editInTime').val('09:00');
        $('#editOutTime').val('18:00');
        $('#editStatus').val('present').trigger('change');
        $('#editAdminReason').val('');
        
        // Store user/date in hidden fields if needed or just use current vars
        window.tempUserId = userId;
        window.tempDate = date;
        
        editModal.show();
        return;
    }

    $.ajax({
        url: `attendance/${id}/edit`,
        method: 'GET',
        success: function(resp) {
            if (resp.success) {
                currentEditId = id;
                $('#editAttendanceId').val(id);
                $('#editEmpName').text(resp.user);
                $('#editInTime').val(resp.attendance.check_in_time ? moment(resp.attendance.check_in_time).format('HH:mm') : '');
                $('#editOutTime').val(resp.attendance.check_out_time ? moment(resp.attendance.check_out_time).format('HH:mm') : '');
                $('#editStatus').val(resp.attendance.status.toLowerCase()).trigger('change');
                $('#editAdminReason').val(resp.attendance.admin_reason || '');
                editModal.show();
            }
        },
        error: function() { alert('Failed to fetch record.'); }
    });
}

/** Global Edit Handler */
$(function() {
    $('#editAttendanceForm').on('submit', function(e) {
        e.preventDefault();
        const id = $('#editAttendanceId').val();
        const btn = $(this).find('button[type="submit"]');
        const oldHtml = btn.html();

        btn.html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...').prop('disabled', true);

        const formData = new FormData(this);
        formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
        if (!id) {
            formData.append('user_id', window.tempUserId);
            formData.append('date', window.tempDate);
            var url = `attendance/store-adjustment`;
        } else {
            var url = `attendance/${id}/update`;
        }

        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(resp) {
                if (resp.success) {
                    const editModal = bootstrap.Modal.getInstance(document.getElementById('editAttendanceModal'));
                    if (editModal) editModal.hide();
                    
                    // Reload UI components
                    if ($.fn.DataTable.isDataTable('#attendanceTable')) $('#attendanceTable').DataTable().draw();
                    if (typeof loadMonthlyRegistry === 'function') loadMonthlyRegistry();
                    if (typeof refreshChart === 'function') refreshChart();
                    
                    if (window.toastr) toastr.success('Record adjusted successfully!');
                    else alert('Record adjusted successfully!');
                } else {
                    if (window.toastr) toastr.error(resp.message || 'Update failed.');
                    else alert(resp.message || 'Update failed.');
                }
            },
            error: function(xhr) { 
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Update failed. Please try again.';
                if (window.toastr) toastr.error(msg);
                else alert(msg);
            },
            complete: function() { btn.html(oldHtml).prop('disabled', false); }
        });
    });

    $('#editStatus').on('change', function() {
        if ($(this).val() === 'present') {
            $('#proofRequiredMarker').removeClass('d-none');
        } else {
            $('#proofRequiredMarker').addClass('d-none');
        }
    });
});

window.showEmployeeSummary = function(name, code, present, absent, late, leave, period) {
    if (!summaryModal) {
        summaryModal = new bootstrap.Modal(document.getElementById('employeeSummaryModal'));
    }
    $('#sumName').text(name);
    $('#sumCode').text(code);
    $('#sumPresent').text(present);
    $('#sumAbsent').text(absent);
    $('#sumLate').text(late);
    $('#sumLeave').text(leave);
    $('#sumMonth').text(period);
    
    const initials = name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
    $('#sumAvatar').text(initials);

    const p = parseInt(present);
    const a = parseInt(absent);
    const l = parseInt(leave);
    const totalDays = p + a + l;
    const score = totalDays > 0 ? Math.round((p / totalDays) * 100) : 0;
    
    $('#sumScore').text(score);
    $('#sumProgress').css('width', score + '%');

    summaryModal.show();
}

/** Utility for file selection visibility */
window.updateFileName = function(input, targetId) {
    const display = document.getElementById(targetId);
    if (input.files.length > 0) {
        display.innerHTML = `<i class="bx bx-file-blank me-2"></i>Selected: <b>${input.files[0].name}</b>`;
        display.classList.remove('d-none');
    } else {
        display.classList.add('d-none');
    }
};

window.submitAttendanceFile = function(input, targetId) {
    const display = document.getElementById(targetId);
    if (!input.files || input.files.length === 0) return;

    display.innerHTML = `<span class="spinner-border spinner-border-sm me-2 text-teal" role="status"></span>Syncing <b>${input.files[0].name}</b>...`;
    display.classList.remove('d-none');

    // Create modal instance if not established
    const previewModal = new bootstrap.Modal(document.getElementById('attendancePreviewModal'));
    const hubModal = bootstrap.Modal.getInstance(document.getElementById('importAttendanceModal'));
    
    const formData = new FormData();
    formData.append('file', input.files[0]);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

    // Perform background AJAX sync
    fetch(input.closest('form').action, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.html) {
            // Populate and show preview modal
            document.getElementById('previewModalBody').innerHTML = data.html;
            document.getElementById('previewRecordsInput').value = JSON.stringify(data.records);
            
            if (hubModal) hubModal.hide();
            previewModal.show();
        } else {
            alert('Processing error. Please check file format.');
            display.classList.add('d-none');
        }
    })
    .catch(error => {
        console.error('Import Error:', error);
        alert('Failed to process file. Please try again.');
        display.classList.add('d-none');
    });
};
