'use strict';
(function checkJQuery() {
  if (typeof jQuery === 'undefined') {
    setTimeout(checkJQuery, 10);
    return;
  }
  $(function () {
    'use strict';
    const select2 = $('.select2');
    if (select2.length) {
      select2.each(function () {
        var $this = $(this);
        $this.select2({
          placeholder: 'Select value',
          dropdownParent: $this.closest('.modal').length ? $this.closest('.modal') : $this.parent()
        });
      });
    }
    // Handle re-initialization when modals are opened to ensure focus works correctly
    $(document).on('shown.bs.modal', '.modal', function () {
      $(this).find('.select2').each(function () {
        var $this = $(this);
        $this.select2({
          dropdownParent: $this.closest('.modal')
        });
      });
    });
  });
})();
