/**
 * HRX Employee Detail View - Premium Interactive Logic
 * Consolidated from monolithic view.blade.php for maximum performance.
 */

'use strict';

// Shared jQuery instance (safe for ES Modules)
const $ = window.jQuery || window.$;

$(function() {
    // Basic setup from original employee-view.js
    var basicInfoForm = $('#basicInfoForm');
    var workInfoForm = $('#workInfoForm');
    var profilePictureForm = $('#profilePictureForm');

    // Sales Target Datepicker
    if ($('#period').length) {
        $('#period').datepicker({
            format: 'yyyy',
            viewMode: 'years',
            minViewMode: 'years',
            autoclose: true,
            clearBtn: true,
            startDate: new Date(new Date().getFullYear(), 0, 1)
        });
    }

    // Incentive Type Toggle
    $('#incentiveType').on('change', function () {
        var value = this.value;
        if (value === 'none') {
            $('#amountDiv').hide();
            $('#percentageDiv').hide();
        } else if (value === 'fixed') {
            $('#amountDiv').show();
            $('#percentageDiv').hide();
        } else if (value === 'percentage') {
            $('#amountDiv').hide();
            $('#percentageDiv').show();
        } else {
            $('#amountDiv').hide();
            $('#percentageDiv').hide();
        }
    });

    // Attendance Type switching
    if (window.attendanceType && window.attendanceType !== 'open') {
        switch (window.attendanceType) {
            case 'geofence': $('#geofenceGroupDiv').show(); getGeofenceGroups(); break;
            case 'ip_address': $('#ipGroupDiv').show(); getIpGroups(); break;
            case 'qr_code': $('#qrGroupDiv').show(); getQrGroups(); break;
            case 'site': $('#siteDiv').show(); getSites(); break;
            case 'dynamic_qr': $('#dynamicQrDiv').show(); getDynamicQrDevices(); break;
        }
    }

    $('#attendanceType').on('change', function () {
        var value = this.value;
        $('#ipGroupDiv, #qrGroupDiv, #dynamicQrDiv, #siteDiv, #geofenceGroupDiv').hide();
        if (value === 'geofence') { $('#geofenceGroupDiv').show(); getGeofenceGroups(); }
        else if (value === 'ipAddress') { $('#ipGroupDiv').show(); getIpGroups(); }
        else if (value === 'staticqr') { $('#qrGroupDiv').show(); getQrGroups(); }
        else if (value == 'site') { $('#siteDiv').show(); getSites(); }
        else if (value == 'dynamicqr') { $('#dynamicQrDiv').show(); getDynamicQrDevices(); }
    });

    // Onboarding Review Stepper Logic
    var stepperEl = document.getElementById('stepperReviewOnboarding');
    if (stepperEl) {
        window.onboardingStepper = new Stepper(stepperEl, {
            linear: false,
            animation: true
        });

        stepperEl.addEventListener('show.bs-stepper', function (event) {
            var index = event.detail.indexStep;
            if (index > 0) $('.btn-prev').fadeIn();
            else $('.btn-prev').fadeOut();

            if (index === 4) {
                $('.btn-next').hide();
                window.updateDecisionButtonsState();
            } else {
                $('.btn-next').show();
                $('#btnApproveAndActivate, #btnSendModification').hide();
            }
        });
    }

    // Tab Scrolling Handler
    const tabWrapper = document.querySelector('.rosemary-nav-tabs');
    if (tabWrapper) {
        const leftBtn = document.querySelector('.rosemary-tab-arrow.left');
        const rightBtn = document.querySelector('.rosemary-tab-arrow.right');
        
        const updateArrows = () => {
            const maxScroll = tabWrapper.scrollWidth - tabWrapper.clientWidth;
            if (maxScroll <= 0) {
                if(leftBtn) leftBtn.style.display = 'none';
                if(rightBtn) rightBtn.style.display = 'none';
                return;
            }
            if(leftBtn) {
                leftBtn.style.display = 'inline-flex';
                leftBtn.disabled = tabWrapper.scrollLeft <= 1;
            }
            if(rightBtn) {
                rightBtn.style.display = 'inline-flex';
                rightBtn.disabled = tabWrapper.scrollLeft >= maxScroll - 1;
            }
        };

        if(leftBtn) leftBtn.addEventListener('click', () => tabWrapper.scrollBy({ left: -220, behavior: 'smooth' }));
        if(rightBtn) rightBtn.addEventListener('click', () => tabWrapper.scrollBy({ left: 220, behavior: 'smooth' }));

        tabWrapper.addEventListener('scroll', updateArrows, { passive: true });
        window.addEventListener('resize', updateArrows);
        updateArrows();
    }
});

/**
 * GLOBAL SCOPE FUNCTIONS
 * (Survive JS modules and accessible via onclick)
 */

window.viewDocumentPopup = function (url, title, docNumber = 'N/A') {
    const modalEl = document.getElementById('modalViewDocument');
    if (!modalEl) return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const contentArea = document.getElementById('modalViewContent');
    const iconEl = document.getElementById('modalViewIcon');

    document.getElementById('modalViewTitle').innerText = title;
    document.getElementById('modalViewSubtitle').innerHTML = `<span class="opacity-75">Ref No / ID:</span> <span class="fw-bold text-white">${docNumber}</span>`;
    
    const downloadBtn = document.getElementById('modalDownloadBtn');
    downloadBtn.href = url;
    downloadBtn.style.display = 'inline-flex';
    
    contentArea.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Loading secure preview...</p></div>';
    modal.show();

    const isImage = /\.(jpeg|jpg|gif|png|webp|svg)/i.test(url);
    setTimeout(() => {
        if (isImage) {
            iconEl.className = 'bx bx-image-alt';
            contentArea.innerHTML = `<div class="p-4 w-100 h-100 d-flex align-items-center justify-content-center" style="background:#f8fafc;"><img src="${url}" class="img-fluid rounded shadow-sm" style="max-height: 70vh; object-fit: contain;"></div>`;
        } else {
            iconEl.className = 'bx bxs-file-pdf';
            contentArea.innerHTML = `<iframe id="viewerIframe" src="${url}" style="width:100%; height:75vh; border:none; background: #fff;"></iframe>`;
        }
    }, 400);
};

window.viewDocumentNumber = function (title, number) {
    const modalEl = document.getElementById('modalViewDocument');
    if (!modalEl) return;
    
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    document.getElementById('modalViewSubtitle').innerHTML = `<span class="opacity-75">Ref No / ID:</span> <span class="fw-bold text-white">${number}</span>`;
    document.getElementById('modalDownloadBtn').style.display = 'none';
    document.getElementById('modalViewIcon').className = 'bx bx-id-card';

    document.getElementById('modalViewContent').innerHTML = `
        <div class="text-center py-5 bg-white w-100 h-100 rounded d-flex flex-column align-items-center justify-content-center" style="min-height: 400px;">
            <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center mb-4" style="width: 100px; height: 100px; background: rgba(18,116,100,0.1);">
                <i class="bx bx-badge-check fs-1" style="color: #127464; font-size: 3rem !important;"></i>
            </div>
            <h2 class="fw-bold text-dark mb-2" style="letter-spacing: 2px;">${number}</h2>
            <p class="text-muted text-uppercase small fw-bold">Authenticated Identity Reference</p>
        </div>
    `;
    modal.show();
};

window.updateDecisionButtonsState = function() {
    const steps = $('.bs-stepper-header .step');
    const currentIdx = steps.index(steps.filter('.active'));
    if (currentIdx !== 4) return;

    const flaggedCount = $('.hitech-toggle-opt.opt-flag.active').length;
    const btnApprove = $('#btnApproveAndActivate');
    const btnModify = $('#btnSendModification');
    const lockInfo = $('#decisionLockInfo');

    if (flaggedCount > 0) {
        btnApprove.stop().fadeOut(200);
        btnModify.stop().fadeIn(300);
        lockInfo.html(`<div class="text-center p-3 rounded-4" style="background: #fff1f2; border: 1px dashed #ef4444;"><i class="bx bxs-error-circle fs-1 text-danger mb-2"></i><div class="fw-bold text-danger">Corrections Required</div><div class="small text-muted">${flaggedCount} section(s) marked for revision.</div></div>`);
    } else {
        btnModify.stop().fadeOut(200);
        btnApprove.stop().fadeIn(300);
        lockInfo.html(`<div class="text-center p-3 rounded-4" style="background: #f0fdf4; border: 1px dashed #22c55e;"><i class="bx bxs-check-shield fs-1 text-success mb-2"></i><div class="fw-bold text-success">Verification Perfect</div><div class="small text-muted">All sections approved. Ready for activation.</div></div>`);
    }
};

window.submitReviewModification = function() {
    const feedback = $('#reviewNotes').val();
    if (!feedback || feedback.trim().length < 10) {
        Swal.fire({ title: 'Feedback Required', text: 'Please provide at least 10 characters of feedback.', icon: 'warning' });
        return;
    }

    const formData = new FormData(document.getElementById('formReviewOnboarding'));
    Swal.fire({
        title: 'Send for Correction?',
        text: 'The employee will be notified to update the flagged sections.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Send'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `${window.baseUrl}employees/onboarding/resubmit/${window.user.id}`,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Sent!', timer: 2000, showConfirmButton: false });
                        setTimeout(() => location.reload(), 2000);
                    }
                }
            });
        }
    });
};

window.approveOnboarding = function(userId) {
    Swal.fire({
        title: 'Approve Profile?',
        text: 'This will activate the employee and grant dashboard access.',
        icon: 'success',
        showCancelButton: true,
        confirmButtonText: 'Yes, Activate'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post(`${window.baseUrl}employees/onboarding/approve/${userId}`, { _token: $('meta[name="csrf-token"]').attr('content') }, function(resp) {
                if (resp.success) {
                    Swal.fire({ icon: 'success', title: 'Activated!', timer: 1500, showConfirmButton: false });
                    setTimeout(() => location.reload(), 1500);
                }
            });
        }
    });
};

// Toggle Handlers (Pills)
$(document).on('click', '.hitech-toggle-opt', function() {
    const btn = $(this);
    const val = btn.data('value');
    const group = btn.closest('.hitech-toggle-pill');
    const section = group.data('section');
    const checkbox = group.find('.section-reject-toggle');
    const remarksBox = $('#remarks-box-' + section);
    
    group.find('.hitech-toggle-opt').removeClass('active');
    btn.addClass('active');
    
    if (val === 'flag') {
        checkbox.prop('checked', true);
        remarksBox.slideDown(300);
    } else {
        checkbox.prop('checked', false);
        remarksBox.slideUp(250);
    }
    window.updateDecisionButtonsState();
});

// Dropdown Fetchers
window.handleManualLeaveCreditSubmission = function(e, formElement) {
    e.preventDefault();
    const $form = $(formElement);
    const $btn = $form.find('button[type="submit"]');
    const originalHtml = $btn.html();

    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Processing...');

    $.ajax({
        url: $form.attr('action'),
        method: 'POST',
        data: new FormData(formElement),
        processData: false,
        contentType: false,
        success: function(resp) {
            if (resp.code === 200 || resp.success) {
                const modalEl = document.getElementById('modalManualLeaveCredit');
                if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                Swal.fire({
                    icon: 'success',
                    title: 'Credit Successful',
                    text: resp.message,
                    customClass: { confirmButton: 'btn btn-primary rounded-pill px-5' }
                }).then(() => location.reload());
            } else {
                Swal.fire('Error', resp.message || 'Validation failed', 'error');
                $btn.prop('disabled', false).html(originalHtml);
            }
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html(originalHtml);
            Swal.fire('System Error', xhr.responseJSON?.message || 'Unable to allot credit.', 'error');
        }
    });
    return false;
};

// Site management helpers
function getGeofenceGroups() {
    $.get(`${window.baseUrl}employee/getGeofenceGroups`, function(resp) {
        let options = '<option value="">Select Geofence Group</option>';
        resp.forEach(item => options += `<option value="${item.id}" ${window.user.geofence_group_id == item.id ? 'selected' : ''}>${item.name}</option>`);
        $('#geofenceGroupId').html(options);
    });
}
function getIpGroups() {
    $.get(`${window.baseUrl}employee/getIpGroups`, function(resp) {
        let options = '<option value="">Select IP Group</option>';
        resp.forEach(item => options += `<option value="${item.id}" ${window.user.ip_address_group_id == item.id ? 'selected' : ''}>${item.name}</option>`);
        $('#ipGroupId').html(options);
    });
}
function getQrGroups() {
    $.get(`${window.baseUrl}employee/getQrGroups`, function(resp) {
        let options = '<option value="">Select QR Group</option>';
        resp.forEach(item => options += `<option value="${item.id}" ${window.user.qr_group_id == item.id ? 'selected' : ''}>${item.name}</option>`);
        $('#qrGroupId').html(options);
    });
}
function getSites() {
    $.get(`${window.baseUrl}employee/getSites`, function(resp) {
        let options = '<option value="">Select Site</option>';
        resp.forEach(item => options += `<option value="${item.id}" ${window.user.site_id == item.id ? 'selected' : ''}>${item.name}</option>`);
        $('#siteId').html(options);
    });
}
function getDynamicQrDevices() {
    $.get(`${window.baseUrl}employee/getDynamicQrDevices`, function(resp) {
        let options = '<option value="">Select Dynamic QR Device</option>';
        resp.forEach(item => options += `<option value="${item.id}" ${window.user.dynamic_qr_device_id == item.id ? 'selected' : ''}>${item.name}</option>`);
        $('#dynamicQrId').html(options);
    });
}

// Stepper Prev/Next Listeners
$(document).on('click', '.btn-next', function() { if(window.onboardingStepper) window.onboardingStepper.next(); });
$(document).on('click', '.btn-prev', function() { if(window.onboardingStepper) window.onboardingStepper.previous(); });
