$(function () {

  // ajax setup
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });
  var dtTable = $('.datatables-visits');

  if (dtTable.length) {

    var dtVisits = dtTable.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'visits/getListAjax',
        data: function (d) {
          d.dateFilter = $('#dateFilter').val();
        }
      },
      columns: [
        {data: null, defaultContent: '', orderable: false, searchable: false},
        {data: 'id'},
        {data: 'user', orderable: false, searchable: false},
        {data: 'client_name'},
        {data: 'created_at'},
        {data: 'image', orderable: false, searchable: false},
        {data: 'actions', orderable: false, searchable: false}
      ],
      order: [[1, 'desc']],
      dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
      lengthMenu: [7, 10, 20, 50, 70, 100],
      language: {
        search: '',
        searchPlaceholder: 'Search Visits',
        info: 'Displaying _START_ to _END_ of _TOTAL_ entries',
        paginate: {
          next: '<i class="bx bx-chevron-right bx-sm"></i>',
          previous: '<i class="bx bx-chevron-left bx-sm"></i>'
        }
      }
    });
  }

  $('#dateFilter').on('change', function () {
    dtVisits.draw();
  });

  $(document).on('click', '.delete-record', function () {
    var id = $(this).data('id');
    var dtrModal = $('.dtr-bs-modal.show');

    // hide responsive modal in small screen
    if (dtrModal.length) {
      dtrModal.modal('hide');
    }

    // sweetalert for confirmation of delete
    Swal.fire({
      title: 'Are you sure?',
      text: "You won't be able to revert this!",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!',
      customClass: {
        confirmButton: 'btn btn-primary me-3',
        cancelButton: 'btn btn-label-secondary'
      },
      buttonsStyling: false
    }).then(function (result) {
      if (result.value) {
        // delete the data
        $.ajax({
          type: 'DELETE',
          url: `${baseUrl}visits/deleteVisitAjax/${id}`,
          success: function () {
            // success sweetalert
            Swal.fire({
              icon: 'success',
              title: 'Deleted!',
              text: 'The shift has been deleted!',
              customClass: {
                confirmButton: 'btn btn-success'
              }
            });

            dtVisits.draw();
          },
          error: function (error) {
            console.log(error);
          }
        });
      }
    });
  });

  // show visit details
  $(document).on('click', '.show-visit-details', function () {
    var id = $(this).data('id');
    //get data
    $.get(`${baseUrl}visits/getByIdAjax/${id}`, function (response) {
      if (response.status === 'success') {
        var data = response.data;

        $('#userName').text(data.userName);
        $('#userCode').text(data.userCode);
        $('#client').text(data.client);
        $('#createdAt').text(data.createdAt);
        $('#address').text(data.address || 'N/A');
        $('#remarks').text(data.remarks || 'N/A');
        $('#imageUrl').attr('src', data.imageUrl);
      }
      console.log(data);
    });
  });

  $('#dateFilter').on('change', function () {
    date = this.value;
    dtVisits.draw();
  });
});
