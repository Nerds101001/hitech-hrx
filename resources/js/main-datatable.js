'use strict';

// Datatable (jquery)
$(function () {
  $('#datatable').DataTable({
    order: [[0, 'asc']]
  });

  $('.datatable').DataTable({
    order: [[0, 'asc']]
  });
});
