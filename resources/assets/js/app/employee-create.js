$(function () {
  $('#useDefaultPassword').on('change', function () {
    if ($(this).is(':checked')) {
      $('#passwordDiv').hide();
    } else {
      $('#passwordDiv').show();
    }
  });
});
