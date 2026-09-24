/**
 * Role Permissions workbench — dedicated page JS.
 * Independent of role-index.js (that file is not loaded on this page).
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
  });

  var $form = $('#rpForm');

  function recomputeCounts() {
    var totalChecked = 0;
    var totalAll = 0;

    $('tr[data-module]').each(function () {
      var slug = $(this).data('module');
      var boxes = $('.' + slug + '-checkbox');
      var checked = boxes.filter(':checked').length;
      var all = boxes.length;
      totalChecked += checked;
      totalAll += all;

      var $railItem = $('.rp-rail-item[data-target="rp-module-' + slug + '"]');
      var $railCount = $railItem.find('.rp-rail-count');
      $railCount.text(checked + '/' + all);
      $railCount.removeClass('rp-count-full rp-count-partial');
      if (all > 0 && checked === all) {
        $railCount.addClass('rp-count-full');
        $railItem.attr('data-all-selected', '1');
      } else {
        $railItem.attr('data-all-selected', '0');
        if (checked > 0) $railCount.addClass('rp-count-partial');
      }

      var $selectAll = $('.rp-module-select-all[data-module="' + slug + '"]');
      if ($selectAll.length) {
        $selectAll.prop('checked', all > 0 && checked === all);
        $selectAll[0].indeterminate = checked > 0 && checked < all;
      }
    });

    $('#rpSelectedCount').text(totalChecked);
    $('#rpSaveCount').text(totalChecked);
    $('#rpGrantAll').prop('checked', totalAll > 0 && totalChecked === totalAll);
  }

  // Per-module "select all" (the matrix's "All" column)
  $(document).on('change', '.rp-module-select-all', function () {
    var slug = $(this).data('module');
    $('.' + slug + '-checkbox').prop('checked', $(this).is(':checked'));
    recomputeCounts();
  });

  // Any individual permission checkbox (CRUD cell or "Other Permissions" chip)
  $(document).on('change', '.rp-permission-checkbox', recomputeCounts);

  // Global "Grant Full Administrative Access"
  $(document).on('change', '#rpGrantAll', function () {
    $('.rp-permission-checkbox').prop('checked', $(this).is(':checked'));
    recomputeCounts();
  });

  // Persona template: replaces the whole permission set with the preset's own
  $(document).on('change', '#rpPreset', function () {
    var opt = this.options[this.selectedIndex];
    var $hint = $('#rpPresetHint');
    if (!this.value) { $hint.text(''); return; }

    var perms = (opt.getAttribute('data-permissions') || '').split(',').filter(Boolean);
    $('.rp-permission-checkbox').prop('checked', false);
    perms.forEach(function (p) {
      $('.rp-permission-checkbox[value="' + p + '"]').prop('checked', true);
    });
    recomputeCounts();
    $hint.text((opt.getAttribute('title') || '') + '  (' + perms.length + ' permissions applied — adjust below if needed)');
  });

  // Left rail: smooth-scroll to a module's row
  $(document).on('click', '.rp-rail-item', function (e) {
    e.preventDefault();
    var el = document.getElementById($(this).data('target'));
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });

  // Highlight whichever module is currently in view
  if ('IntersectionObserver' in window) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        $('.rp-rail-item').removeClass('rp-active');
        $('.rp-rail-item[data-target="' + entry.target.id + '"]').addClass('rp-active');
      });
    }, { rootMargin: '-15% 0px -70% 0px', threshold: 0 });
    $('tr[data-module], #rp-module-access-channels').each(function () { observer.observe(this); });
  }

  // Live search across module names and "Other Permissions" labels
  $('#rpSearch').on('keyup', function () {
    var q = $.trim($(this).val()).toLowerCase();
    if (!q) {
      $('[data-search], .rp-rail-item, .rp-chip').removeClass('rp-hidden-by-search');
      return;
    }
    $('tr[data-module], #rp-module-access-channels').each(function () {
      var $row = $(this);
      var moduleMatches = ($row.data('search') || '').toString().indexOf(q) !== -1;
      var anyChipMatch = false;

      $row.find('.rp-chip').each(function () {
        var chipMatches = moduleMatches || ($(this).data('search') || '').toString().indexOf(q) !== -1;
        $(this).toggleClass('rp-hidden-by-search', !chipMatches);
        if (chipMatches) anyChipMatch = true;
      });

      var show = moduleMatches || anyChipMatch;
      $row.toggleClass('rp-hidden-by-search', !show);
      var targetId = $row.attr('id');
      $('.rp-rail-item[data-target="' + targetId + '"]').toggleClass('rp-hidden-by-search', !show);
    });
  });

  // Save
  $('#rpSaveBtn').on('click', function () {
    var name = $.trim($('#rpRoleName').val());
    if (!name) {
      Swal.fire({ icon: 'warning', title: 'Role name required', text: 'Give this role a name before saving.' });
      return;
    }

    var $btn = $(this).prop('disabled', true);
    $.ajax({
      url: baseUrl + 'roles/addOrUpdateAjax',
      type: 'POST',
      data: $form.serialize(),
      success: function () {
        showSuccessToast('Role permissions saved successfully');
        setTimeout(function () {
          window.location.href = baseUrl + 'roles';
        }, 900);
      },
      error: function (err) {
        $btn.prop('disabled', false);
        var response = err.responseJSON;
        Swal.fire({
          title: 'Failed',
          text: response && response.data ? response.data : 'Forbidden (403) or session expired. Please refresh.',
          icon: 'error',
          customClass: { confirmButton: 'btn btn-success' }
        });
      }
    });
  });

  recomputeCounts();
});
