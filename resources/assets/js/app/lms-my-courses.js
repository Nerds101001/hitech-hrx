'use strict';

$(function () { // jQuery document ready

  // --- Selectors ---
  const categoryFilter = $('#filter-category');
  const hideCompletedSwitch = $('#filter-hide-completed');
  const courseGrid = $('#my-courses-grid');
  const courseCards = $('.course-card-wrapper'); // Select all course cards initially
  const noCoursesMessage = $('#noCoursesMessage');
  // const searchInput = $('#myCourseSearch'); // Uncomment if using search

  // --- Initialize Plugins ---
  if (categoryFilter.length) {
    categoryFilter.select2({
      placeholder: 'All Categories',
      allowClear: true,
      // No need for dropdownParent here unless it's in a modal
    });
  }

  // --- Filtering Function ---
  function filterCourses() {
    const selectedCategory = categoryFilter.val(); // Get selected category ID (string or null)
    const hideCompleted = hideCompletedSwitch.is(':checked'); // Get switch state (boolean)
    // const searchTerm = searchInput.val()?.toLowerCase() || ''; // Uncomment if using search

    let visibleCount = 0;

    // Loop through each course card
    courseCards.each(function() {
      const card = $(this);
      const cardCategory = card.data('category-id')?.toString() || ''; // Get category ID as string
      const cardCompleted = card.data('completed') === true || card.data('completed') === 'true'; // Get completed status as boolean
      // const cardTitle = card.find('.h5').text().toLowerCase(); // Uncomment if using search
      // const cardDesc = card.find('.card-text').first().text().toLowerCase(); // Uncomment if using search

      let matchesCategory = !selectedCategory || cardCategory === selectedCategory;
      let matchesCompleted = !hideCompleted || !cardCompleted;
      // let matchesSearch = !searchTerm || cardTitle.includes(searchTerm) || cardDesc.includes(searchTerm); // Uncomment if using search

      // Check if card matches all active filters
      // if (matchesCategory && matchesCompleted && matchesSearch) { // Uncomment if using search
      if (matchesCategory && matchesCompleted) {
        card.show(); // Show the card's column
        visibleCount++;
      } else {
        card.hide(); // Hide the card's column
      }
    });

    // Show/hide the "no courses" message based on visibility
    noCoursesMessage.toggle(visibleCount === 0);
  }

  // --- Event Listeners for Filters ---
  categoryFilter.on('change', filterCourses);
  hideCompletedSwitch.on('change', filterCourses);

  // --- Event Listener for Search (Optional - Uncomment if using) ---
  /*
  let searchTimeout;
  searchInput.on('input', function() {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(filterCourses, 300); // Debounce search
  });
  */

  // --- Initial Filter on Page Load ---
  // Optional: If you want filters to apply immediately based on default values
  // filterCourses(); // Uncomment if needed

}); // End Document Ready
