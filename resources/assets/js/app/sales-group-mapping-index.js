'use strict';

$(function () {
  var dtTable = $('.datatables-sales-group-mappings');

  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  if (dtTable.length) {
    var dtMapping = dtTable.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'sales-group-mappings/getListAjax',
        data: function (d) {
          d.searchTerm = $('#customSearchInput').val();
          var status = $('input[name="statusFilter"]:checked').val();
          d.statusFilter = status && status !== 'All' ? status : '';
        },
        error: function (xhr, error, code) {
          console.log('Error: ' + error);
          console.log('Code: ' + code);
          console.log('Response: ' + xhr.responseText);
        }
      },
      columns: [
        { data: '' },
        { data: 'tally_salesperson' },
        { data: 'user_name' },
        { data: 'status_display' },
        { data: 'note' },
        { data: 'actions' }
      ],
      columnDefs: [
        {
          className: 'control',
          searchable: false,
          orderable: false,
          responsivePriority: 2,
          targets: 0,
          render: function () { return ''; }
        },
        {
          targets: 1,
          render: function (data, type, full) {
            return '<span class="fw-semibold">' + (full.tally_salesperson || '') + '</span>';
          }
        },
        {
          targets: 2,
          render: function (data, type, full) {
            return full.user_name || '<span class="text-muted">— unassigned —</span>';
          }
        },
        { targets: 3, orderable: false },
        {
          targets: 4,
          render: function (data, type, full) {
            return full.note ? '<span class="text-muted small">' + full.note + '</span>' : '';
          }
        },
        { targets: 5, orderable: false, searchable: false, className: 'text-center' }
      ],
      order: [[1, 'asc']],
      lengthMenu: [10, 25, 50, 100],
      pageLength: 25
    });

    $('#customSearchInput').on('keyup', function () { dtMapping.draw(); });
    $('#customSearchBtn').on('click', function () { dtMapping.draw(); });
    $('input[name="statusFilter"]').on('change', function () { dtMapping.draw(); });
    $('#customLengthMenu').on('change', function () {
      dtMapping.page.len($(this).val()).draw();
    });
  }

  $('.select2-users').select2({
    dropdownParent: $('#modalEditSalesGroupMapping'),
    placeholder: '— none —',
    allowClear: true,
    width: '100%'
  });

  var editModal = $('#modalEditSalesGroupMapping');

  $(document).on('click', '.edit-record', function () {
    var id = $(this).data('id');
    $.get(baseUrl + 'sales-group-mappings/getByIdAjax/' + id, function (response) {
      var data = response.data;
      $('#mapping_id').val(data.id);
      $('#mapping_tally_salesperson').val(data.tally_salesperson);
      $('#mapping_user_id').val(data.user_id || '').trigger('change');
      $('#mapping_status').val(data.status);
      $('#mapping_note').val(data.note || '');
    });
  });

  $('#salesGroupMappingForm').on('submit', function (e) {
    e.preventDefault();

    var status = $('#mapping_status').val();
    var userId = $('#mapping_user_id').val();
    if (status === 'confirmed' && !userId) {
      Swal.fire({ icon: 'warning', title: 'Pick a person first', text: 'A mapping can only be confirmed once it points at someone.' });
      return;
    }

    $.ajax({
      data: $(this).serialize(),
      url: baseUrl + 'sales-group-mappings/addOrUpdateAjax',
      type: 'POST',
      success: function (response) {
        if (response.status === 'success') {
          editModal.modal('hide');
          Swal.fire({ icon: 'success', title: 'Saved', text: response.data, customClass: { confirmButton: 'btn btn-hitech' } });
          dtMapping.draw();
        }
      },
      error: function (err) {
        var body;
        try { body = JSON.parse(err.responseText); } catch (e) { body = null; }
        Swal.fire({
          icon: 'error',
          title: 'Could not save',
          text: (body && (body.data || (body.errors && Object.values(body.errors)[0][0]))) || 'Please try again.',
          customClass: { confirmButton: 'btn btn-hitech' }
        });
      }
    });
  });

  editModal.on('hidden.bs.modal', function () {
    $('#salesGroupMappingForm')[0].reset();
    $('#mapping_user_id').val('').trigger('change');
  });
});
