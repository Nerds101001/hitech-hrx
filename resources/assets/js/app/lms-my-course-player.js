'use strict';

// Datatable (jquery)
$(function () {

  // --- Selectors ---
  const lessonListContainer = document.getElementById('lesson-list-container');
  const lessonContentArea = document.getElementById('lesson-content-area');
  // Specific content divs
  const textContentDiv = document.getElementById('lesson-text-content');
  const videoPlayerContainer = document.getElementById('lesson-video-player-container');
  const videoPlayerElement = document.getElementById('lesson-video-player');
  const pdfContentDiv = document.getElementById('lesson-pdf-content');
  const pdfLink = document.getElementById('lesson-pdf-link');
  const linkContentDiv = document.getElementById('lesson-external-link-content');
  const externalLink = document.getElementById('lesson-external-link');
  const quizContentDiv = document.getElementById('lesson-quiz-content');
  const initialMessageDiv = document.getElementById('lesson-initial-message');
  // Progress indicator
  const progressIndicator = document.getElementById('lesson-progress-indicator');

  let plyrInstance = null; // To hold Plyr instance

  // --- CSRF Setup for Fetch API ---
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  // --- Initialize Plyr ---
  if (videoPlayerElement && typeof Plyr !== 'undefined') {
    try {
      // Basic init, source will be set later
      plyrInstance = new Plyr(videoPlayerElement);
    } catch (e) { console.error("Plyr initialization error:", e); }
  } else if (typeof Plyr === 'undefined'){
    console.warn("Plyr library not found.");
  }

  // --- Helper: Update Progress Display ---
  function updateProgressIndicator() {
    if (!progressIndicator || !lessonListContainer) return;
    const totalLessons = lessonListContainer.querySelectorAll('.lesson-item').length;
    const completedLessons = lessonListContainer.querySelectorAll('.lesson-complete-toggle:checked').length;
    progressIndicator.textContent = `${completedLessons} / ${totalLessons} lessons completed`;
  }
  // Initial update
  updateProgressIndicator();


  // --- Helper: Show Active Content Area ---
  function showContentArea(areaToShow) {
    // Hide all content areas first
    [initialMessageDiv, textContentDiv, videoPlayerContainer, pdfContentDiv, linkContentDiv, quizContentDiv].forEach(div => {
      if (div) div.style.display = 'none';
    });
    // Destroy Plyr instance if hiding video player
    if (areaToShow !== videoPlayerContainer && plyrInstance && !plyrInstance.isDestroyed) {
      // Check if source needs explicit stopping/resetting before destroy
      try {plyrInstance.stop(); plyrInstance.source = null;} catch(e){}
      //plyrInstance.destroy(); // Re-initializing might be better than destroy/recreate
    }

    // Show the requested area
    if (areaToShow) areaToShow.style.display = 'block';
  }


  // --- Event Listener: Lesson Link Click ---
  if (lessonListContainer) {
    lessonListContainer.addEventListener('click', function (event) {
      const lessonLink = event.target.closest('.lesson-link');
      if (!lessonLink) return; // Exit if click wasn't on a lesson link area

      event.preventDefault(); // Prevent default label behavior if it interferes

      const lessonItem = lessonLink.closest('.lesson-item');
      if (!lessonItem) return;

      console.log(lessonItem);

      // Get data from attributes
      const lessonId = lessonItem.dataset.lessonId;
      const contentType = lessonItem.dataset.contentType;
      const contentData = lessonItem.dataset.contentData;
      const lessonTitle = lessonItem.dataset.lessonTitle;

      console.log(lessonItem.dataset);
      console.log(`Data Retrieved - ID: ${lessonId}, Type: ${contentType}, Title: ${lessonTitle}`);
      console.log(`Content Data:`, contentData);

      // Highlight active lesson
      lessonListContainer.querySelectorAll('.lesson-item.active').forEach(el => el.classList.remove('active'));
      lessonItem.classList.add('active');

      // --- Display Content Based on Type ---
      showContentArea(null); // Hide all first

      switch (contentType) {
        case 'text':
        case 'video_embed': // Display embed code as text/html for now
          if (textContentDiv) {
            // Directly set innerHTML for text/embed. Ensure backend sanitizes if needed.
            textContentDiv.innerHTML = contentData || '<p>No content available.</p>';
            showContentArea(textContentDiv);
          }
          break;

        case 'video_file':
          if (videoPlayerContainer && plyrInstance && contentData) {
            try {
              // Update Plyr source
              plyrInstance.source = {
                type: 'video',
                sources: [{ src: contentData, type: 'video/mp4' }], // Adjust type if needed
                // title: lessonTitle // Optional
              };
              showContentArea(videoPlayerContainer);
            } catch (e) {
              console.error("Error setting Plyr source:", e);
              showContentArea(initialMessageDiv); // Show default on error
              showErrorToast('Could not load video.'); // Use global toast
            }
          } else {
            showContentArea(initialMessageDiv);
            showErrorToast('Video player not ready or video link missing.');
          }
          break;

        case 'file_upload': // Treat as downloadable link
        case 'file':
          if (pdfContentDiv && pdfLink && contentData) {
            pdfLink.href = contentData; // Use the URL directly
            pdfLink.innerHTML = `View/Download File <i class="bx bx-link-external ms-1"></i>`;
            showContentArea(pdfContentDiv);
          }
          break;
        case 'link': // External link
          const targetDiv = (contentType === 'file_upload') ? pdfContentDiv : linkContentDiv;
          const targetLink = (contentType === 'file_upload') ? pdfLink : externalLink;
          const linkText = (contentType === 'file_upload') ? 'Download/View File' : 'Open Link';

          if (targetDiv && targetLink && contentData) {
            targetLink.href = contentData;
            targetLink.innerHTML = `${linkText} <i class="bx bx-link-external ms-1"></i>`;
            showContentArea(targetDiv);
          } else {
            showContentArea(initialMessageDiv);
            showErrorToast('Link/File URL is missing.');
          }
          break;

        case 'quiz':
          if (quizContentDiv) showContentArea(quizContentDiv);
          break;

        default:
          // Fallback to initial message if type is unknown or content missing
          if (initialMessageDiv) showContentArea(initialMessageDiv);
          console.warn(`Unknown or unhandled content type: ${contentType}`);
      }
    });
  }


  // --- Event Listener: Mark Lesson Complete Checkbox ---
  if (lessonListContainer) {
    lessonListContainer.addEventListener('change', async function (event) {
      const checkbox = event.target;
      if (!checkbox || !checkbox.classList.contains('lesson-complete-toggle')) {
        return; // Ignore changes from other inputs
      }

      const lessonId = checkbox.dataset.lessonId;
      const isChecked = checkbox.checked;
      const lessonItem = checkbox.closest('.lesson-item');

      if (!lessonId) return;

      checkbox.disabled = true; // Disable during request
      const originalState = !isChecked; // State before user clicked

      // Construct URL
      const url = markCompleteUrlTemplate.replace(':lessonId', lessonId); // URL from Blade

      try {
        const response = await fetch(url, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrfToken, // CSRF token from Blade
            'Accept': 'application/json',
            'Content-Type': 'application/json' // Although no body needed here usually
          },
          // body: JSON.stringify({}) // No body needed usually for simple toggle
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
          throw new Error(data.message || 'Failed to update status');
        }

        // Success!
        // showSuccessToast(data.message || 'Progress saved'); // Use global toast
        lessonItem?.classList.toggle('completed', isChecked); // Ensure visual consistency
        updateProgressIndicator(); // Update header progress

      } catch (error) {
        console.error("Error marking lesson complete:", error);
        showErrorToast(error.message || 'Could not save progress'); // Use global toast
        // Revert checkbox on error
        checkbox.checked = originalState;
        lessonItem?.classList.toggle('completed', originalState);
      } finally {
        checkbox.disabled = false; // Re-enable checkbox
      }
    });
  }

  // --- Sticky Sidebar (Basic Example - requires specific CSS or Bootstrap utilities) ---
  // This assumes Bootstrap 5 sticky-top utilities are used on the right column element
  // No extra JS might be needed if Bootstrap handles it.
  // If manual sticky is needed:
  /*
  const contentColumn = document.querySelector('.col-lg-8'); // Adjust selector
  const sidebarColumn = document.querySelector('.col-lg-4 .accordion'); // Adjust selector
  if (contentColumn && sidebarColumn) {
      const offsetTop = sidebarColumn.offsetTop; // Initial offset
      window.addEventListener('scroll', () => {
           if (window.pageYOffset > offsetTop) {
               sidebarColumn.classList.add('stick-top'); // Apply sticky class
                // Adjust top position based on fixed navbar height if necessary
                // sidebarColumn.style.top = 'YOUR_NAVBAR_HEIGHTpx';
           } else {
                sidebarColumn.classList.remove('stick-top');
                // sidebarColumn.style.top = '';
           }
      });
  }
  */

}); // End DOMContentLoaded
