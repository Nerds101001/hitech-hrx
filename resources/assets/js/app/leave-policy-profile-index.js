'use strict';

$(function () {
  const profileForm = $('#profileForm');
  const profileModal = $('#modalAddOrUpdateLeavePolicyProfile');

  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // Handle Add New button
  $('.add-new').on('click', function () {
    profileForm[0].reset();
    $('#profile_id').val('');
    $('#modalProfileLabel').html('Create Leave Policy Profile');
    $('.sat-checkbox, .btn-check').prop('checked', false);
    $('#all_saturday_off_toggle').prop('checked', false);
    $('#late_arrival_limit, #half_day_limit').val('');
    $('#late_arrival_type, #half_day_type').val('half_day');
    $('.type-applicable-toggle').prop('checked', false);
    $('.collapse').collapse('hide');
  });

  // Handle Edit button
  $(document).on('click', '.edit-record', function () {
    const id = $(this).data('id');
    $('#modalProfileLabel').html('Edit Leave Policy Profile');

    $.get(`${baseUrl}leavePolicyProfiles/getProfileAjax/${id}`, function (data) {
      $('#profile_id').val(data.id);
      $('#profile_name').val(data.name);
      $('#profile_description').val(data.description);

      $('.sat-checkbox, .btn-check').prop('checked', false);
      if (data.saturday_off_config) {
        data.saturday_off_config.forEach(day => {
          $(`#sat_${day}`).prop('checked', true);
        });
        if (data.saturday_off_config.length === 5) $('#all_saturday_off_toggle').prop('checked', true);
      }

      // Populate Deduction Config
      if (data.deduction_config) {
        $('#late_arrival_limit').val(data.deduction_config.late_arrival_limit || '');
        $('#late_arrival_type').val(data.deduction_config.late_arrival_type || 'half_day');
        $('#half_day_limit').val(data.deduction_config.half_day_limit || '');
        $('#half_day_type').val(data.deduction_config.half_day_type || 'half_day');
      }

      $('.type-applicable-toggle').prop('checked', false);
      $('.collapse').collapse('hide');

      data.rules.forEach(rule => {
        const toggle = $(`#is_applicable_${rule.leave_type_id}`);
        toggle.prop('checked', true);
        
        const collapse = $(`#collapseType${rule.leave_type_id}`);
        collapse.collapse('show');

        if (rule.short_leave_hours) {
           $(`input[name="rules[${rule.leave_type_id}][short_leave_hours]"]`).val(rule.short_leave_hours);
           $(`input[name="rules[${rule.leave_type_id}][short_leave_per_month]"]`).val(rule.short_leave_per_month);
        } else {
           $(`input[name="rules[${rule.leave_type_id}][max_per_month]"]`).val(rule.max_per_month);
           $(`input[name="rules[${rule.leave_type_id}][max_per_year]"]`).val(rule.max_per_year);
           $(`input[name="rules[${rule.leave_type_id}][max_consecutive_days]"]`).val(rule.max_consecutive_days);
           $(`select[name="rules[${rule.leave_type_id}][is_carry_forward]"]`).val(rule.is_carry_forward ? 1 : 0);
           $(`input[name="rules[${rule.leave_type_id}][carry_forward_max_days]"]`).val(rule.carry_forward_max_days);
           $(`input[name="rules[${rule.leave_type_id}][wfh_days_entitlement]"]`).val(rule.wfh_days_entitlement);
           $(`input[name="rules[${rule.leave_type_id}][off_days_entitlement]"]`).val(rule.off_days_entitlement);
        }

        $(`#is_married_only_${rule.leave_type_id}`).prop('checked', rule.is_married_only);
        
        // Populate new eligibility dropdowns
        $(`select[name="rules[${rule.leave_type_id}][applicable_gender]"]`).val(rule.applicable_gender ?? 'all');
        $(`select[name="rules[${rule.leave_type_id}][applicable_marital_status]"]`).val(rule.applicable_marital_status ?? 'all');

        if (rule.tenure_required_months) {
            $(`input[name="rules[${rule.leave_type_id}][tenure_required_months]"]`).val(rule.tenure_required_months);
        }
        
        // Populate tenure consecutive upgrade from direct column or legacy tiers
        if (rule.tenure_consecutive_allowed) {
             $(`input[name="rules[${rule.leave_type_id}][tenure_consecutive_allowed]"]`).val(rule.tenure_consecutive_allowed);
        } else if (rule.tenure_tiers && rule.tenure_tiers.length > 0) {
             $(`input[name="rules[${rule.leave_type_id}][tenure_consecutive_allowed]"]`).val(rule.tenure_tiers[0].consecutive);
        }
      });
    });
  });

  // Profile Form Submission
  profileForm.on('submit', function (e) {
    e.preventDefault();
    $.ajax({
      url: `${baseUrl}leavePolicyProfiles/addOrUpdateAjax`,
      type: 'POST',
      data: $(this).serialize(),
      success: function (response) {
        if (response.code === 200) {
          profileModal.modal('hide');
          location.reload();
        }
      }
    });
  });

  // Manual Credit Form Submission
  $('#manualCreditForm').on('submit', function (e) {
    e.preventDefault();
    const submitBtn = $(this).find('button[type="submit"]');
    const originalHtml = submitBtn.html();
    submitBtn.prop('disabled', true).html('<i class="bx bx-loader fs-4 animate-spin me-2"></i> Processing...');

    $.ajax({
      url: `${baseUrl}leavePolicyProfiles/addManualCreditAjax`,
      type: 'POST',
      data: $(this).serialize(),
      success: function (response) {
        if (response.code === 200) {
          $('#modalManualLeaveCredit').modal('hide');
          Swal.fire({
            icon: 'success',
            title: 'Credit Allotted!',
            text: response.message,
            customClass: { confirmButton: 'btn btn-hitech' }
          });
          $('#manualCreditForm')[0].reset();
        }
        submitBtn.prop('disabled', false).html(originalHtml);
      },
      error: function () {
        Swal.fire({ icon: 'error', title: 'Failed!', text: 'Error allotting credit.' });
        submitBtn.prop('disabled', false).html(originalHtml);
      }
    });
  });
});
