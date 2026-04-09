$(function () {
  var dtTable = $('.datatables-payroll');

  if (dtTable.length) {
    var dtPayroll = dtTable.DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: baseUrl + 'payroll/getListAjax',
        data: function (d) {
          d.dateFilter = $('#dateFilter').val();
          d.employeeFilter = $('#employeeFilter').val();
          d.statusFilter = $('#statusFilter').val();
        }
      },
      columns: [
        {data: 'id'},
        {data: 'user'},
        {data: 'period'},
        {data: 'basic_salary'},
        {data: 'gross_salary'},
        {data: 'net_salary'},
        {data: 'status'},
        {data: 'actions', orderable: false, searchable: false}
      ],
      order: [[1, 'desc']]
    });
  }

  $('.filter-input').on('change', function () {
    dtPayroll.draw();
  });
});
