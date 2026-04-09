/**
 * HRMS App Calendar Logic - Final Version with Attendee Count/List
 */
'use strict';

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';

document.addEventListener('DOMContentLoaded', function () {

  // --- Global Setup & Config ---
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  if (!csrfToken) { console.error("CSRF Token meta tag not found!"); }
  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrfToken } });

  const calendarUrls = {
    fetch: '/calendar/events',
    store: '/calendar/events',
    details: '/calendar/events/:id/details',
    update: '/calendar/events/:id',
    destroy: '/calendar/events/:id',
    searchClients: '/calendar/events/searchClientsAjax',
  };
  const eventTypeColors = {
    'Meeting': '#007bff',
    'Training': '#ffc107',
    'Leave': '#6c757d',
    'Holiday': '#28a745',
    'Deadline': '#dc3545',
    'Company Event': '#17a2b8',
    'Interview': '#6f42c1',
    'Onboarding Session': '#fd7e14',
    'Performance Review': '#20c997',
    'Client Appointment': '#6610f2', // Indigo example
    'Other': '#6c757d'
  };

  const defaultEventColor = '#6c757d';

  const defaultAvatar = "{{ asset('assets/img/avatars/default.png') }}"; // Default path if needed by JS fallback

  // --- DOM Element References ---
  const calendarEl = document.getElementById('calendar');
  const eventModalElement = document.getElementById('eventModal');
  const eventModal = new bootstrap.Modal(eventModalElement);
  const eventForm = document.getElementById('eventForm');
  const eventModalLabel = document.getElementById('eventModalLabel');
  const saveEventBtn = document.getElementById('saveEventBtn');
  const deleteEventBtn = document.getElementById('deleteEventBtn');
  const viewEventModalElement = document.getElementById('viewEventModal');
  const viewEventModal = new bootstrap.Modal(viewEventModalElement);
  const editEventBtnView = document.getElementById('editEventBtnView');

  // Edit Modal Form Fields
  const eventIdInput = document.getElementById('event_id');
  const eventTitleInput = document.getElementById('event_title');
  const eventTypeInput = document.getElementById('event_type');
  const eventStartInput = document.getElementById('event_start');
  const eventEndInput = document.getElementById('event_end');
  const allDayCheckbox = document.getElementById('all_day');
  const attendeesSelect = $('#attendee_ids');
  const locationInput = document.getElementById('event_location');
  const descriptionInput = document.getElementById('event_description');
  const meetingLinkInput = document.getElementById('meeting_link'); // New field
  const clientIdSelect = $('#client_id'); // New jQuery selector for Client Select2
  const clientSelectionArea = document.getElementById('client_selection_area'); // Div containing client select
  const clientRequiredIndicator = document.getElementById('client_required_indicator'); // Span for required indicator

  // View Modal Display Fields
  const viewEventTitle = document.getElementById('viewEventTitle');
  const viewEventType = document.getElementById('viewEventType');
  const viewEventStart = document.getElementById('viewEventStart');
  const viewEventEnd = document.getElementById('viewEventEnd');
  const viewEventAllDay = document.getElementById('viewEventAllDay');
  const viewEventAttendeesList = document.getElementById('viewEventAttendeesList'); // UL element
  const viewEventAttendeesCount = document.getElementById('viewEventAttendeesCount'); // Span for count
  const viewEventLocation = document.getElementById('viewEventLocation');
  const viewEventDescription = document.getElementById('viewEventDescription');
  const viewEventClient = document.getElementById('viewEventClient'); // New field
  const viewEventMeetingLink = document.getElementById('viewEventMeetingLink'); // New field
  const viewClientLabel = document.getElementById('viewClientLabel');
  const viewClientValueArea = document.getElementById('viewClientValueArea');
  const viewMeetingLinkLabel = document.getElementById('viewMeetingLinkLabel');
  const viewMeetingLinkValueArea = document.getElementById('viewMeetingLinkValueArea');

  // --- Plugin Initialization ---
  let flatpickrStart, flatpickrEnd;
  try {
    if (eventStartInput) { flatpickrStart = flatpickr(eventStartInput, { enableTime: true, dateFormat: 'Y-m-d H:i', allowInput: true }); }
    if (eventEndInput) { flatpickrEnd = flatpickr(eventEndInput, { enableTime: true, dateFormat: 'Y-m-d H:i', allowInput: true }); }
  } catch (e) { console.error("Error initializing Flatpickr:", e); }

  if (attendeesSelect.length) {
    try {
      function formatUserSelection(user) { return user.text; }
      function formatUserDropdown(user) { if (!user.id) { return user.text; } return $('<span>' + user.text + '</span>'); }
      attendeesSelect.select2({ placeholder: 'Select Attendees', dropdownParent: $('#eventModal .modal-body'), closeOnSelect: false, templateResult: formatUserDropdown, templateSelection: formatUserSelection, escapeMarkup: m => m });
    } catch(e) { console.error("Error initializing Select2:", e); }
  }



  // Initialize Select2 for Event Type (if using select2 class in Blade)
  const eventTypeSelect = $('#event_type');
  if (eventTypeSelect.length && eventTypeSelect.hasClass('select2')) {
    try {
      eventTypeSelect.select2({
        placeholder: 'Select Type...',
        dropdownParent: $('#eventModal .modal-body')
      });
    } catch (e) { console.error("Error initializing Event Type Select2:", e); }
  }

  // Initialize Select2 for Clients with AJAX
  if (clientIdSelect.length) {
    try {
      clientIdSelect.select2({
        placeholder: 'Search & Select Client...',
        dropdownParent: $('#eventModal .modal-body'), // Attach dropdown to modal
        allowClear: true,
        minimumInputLength: 2, // Start searching after 2 characters
        ajax: {
          url: calendarUrls.searchClients, // Use URL from Blade/Config
          dataType: 'json',
          delay: 250, // Wait 250ms after typing stops
          data: function (params) {
            return {
              q: params.term, // Search term
              page: params.page || 1
            };
          },
          processResults: function (data, params) {
            params.page = params.page || 1;
            return {
              results: data.results, // items = array of {id: '', text: ''}
              pagination: {
                more: data.pagination.more // boolean
              }
            };
          },
          cache: true
        },
        escapeMarkup: markup => markup, // Let our custom formatter work
        // templateResult: formatClientResult, // Optional custom formatting
        // templateSelection: formatClientSelection // Optional custom formatting
      });
    } catch (e) { console.error("Error initializing Client Select2:", e); }
  }

  // --- FullCalendar Instance ---
  if (!calendarEl) { console.error("Calendar element #calendar not found!"); return; }
  const calendar = new Calendar(calendarEl, {
    plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
    events: fetchEvents,
    navLinks: true, selectable: true, selectMirror: true, editable: true,
    dayMaxEvents: true, weekends: true,
    eventClassNames: ({ event }) => [`event-type-${(event.extendedProps.eventType || 'other').toLowerCase().replace(/\s+/g, '-')}`],
    eventDidMount: (info) => {
      const manualColor = info.event.backgroundColor || info.event.borderColor;
      const typeColor = eventTypeColors[info.event.extendedProps.eventType];
      const finalColor = manualColor || typeColor || '#6c757d';
      info.el.style.backgroundColor = finalColor; info.el.style.borderColor = finalColor;
      if (info.event.extendedProps.description && typeof bootstrap?.Tooltip === 'function') { new bootstrap.Tooltip(info.el, { title: info.event.extendedProps.description, placement: 'top', trigger: 'hover', container: 'body' }); }
    },
    select: handleDateSelect, eventClick: handleEventClick, eventDrop: handleEventDropResize, eventResize: handleEventDropResize
  });
  try { calendar.render(); } catch(e) { console.error("Error rendering FullCalendar:", e); }

  // --- Core Functions ---

  function fetchEvents(fetchInfo, successCallback, failureCallback) {
    $.ajax({ url: calendarUrls.fetch, method: 'GET', data: { start: fetchInfo.startStr, end: fetchInfo.endStr },
      success: data => successCallback(data || []),
      error: jqXHR => { console.error("Error fetching events:", jqXHR); showNotification('error', 'Could not load events.'); if (failureCallback) failureCallback(jqXHR); }
    });
  }

  function handleDateSelect(selectInfo) {
    resetEditModal();
    eventModalLabel.textContent = 'Add New Event';
    saveEventBtn.textContent = 'Add Event';
    deleteEventBtn.classList.add('d-none');
    eventIdInput.value = '';

    // Pre-fill dates, handle allDay
    const startDate = moment(selectInfo.start);
    const endDate = moment(selectInfo.end);
    const isAllDay = selectInfo.allDay;

    flatpickrStart?.setDate(startDate.format('YYYY-MM-DD HH:mm'), true);
    // For all-day clicks spanning multiple days, end date is exclusive in FullCalendar. Adjust if needed.
    // For single all-day click, end is often null or same as start. Set end date based on that.
    if (isAllDay) {
      // If end is significantly after start (multi-day select), subtract one day for inclusive end display
      if (endDate.isValid() && endDate.diff(startDate, 'days') >= 1) {
        flatpickrEnd?.setDate(endDate.subtract(1, 'day').format('YYYY-MM-DD HH:mm'), true);
      } else {
        flatpickrEnd?.clear(); // Clear end date for single all-day event
      }
    } else {
      flatpickrEnd?.setDate(endDate.format('YYYY-MM-DD HH:mm'), true);
    }
    allDayCheckbox.checked = isAllDay;
    toggleEndTimeVisibility(!isAllDay); // Hide end time if all-day

    eventModal.show();
  }

  function handleEventClick(clickInfo) {
    const eventId = clickInfo.event.id; if (!eventId) return;
    const detailUrl = calendarUrls.details.replace(':id', eventId);
    $.ajax({ url: detailUrl, method: 'GET',
      success: data => {
        viewEventTitle.textContent = data.eventTitle || 'N/A';
        viewEventType.textContent = data.eventType || 'N/A';
        viewEventStart.textContent = data.eventStart ? moment(data.eventStart).format('MMM D, YYYY, h:mm A') : 'N/A';
        viewEventEnd.textContent = data.eventEnd ? moment(data.eventEnd).format('MMM D, YYYY, h:mm A') : 'Not Set';
        viewEventAllDay.textContent = data.allDay ? 'Yes' : 'No';
        viewEventLocation.textContent = data.eventLocation || 'N/A';
        viewEventDescription.textContent = data.eventDescription || 'N/A';

        // Populate Client (Show/Hide based on data)
        if(data.clientName) {
          viewEventClient.textContent = data.clientName;
          viewClientLabel.style.display = '';
          viewClientValueArea.style.display = '';
        } else {
          viewEventClient.textContent = '';
          viewClientLabel.style.display = 'none';
          viewClientValueArea.style.display = 'none';
        }

        // Populate Meeting Link (Show/Hide based on data)
        if(data.meetingLink) {
          viewEventMeetingLink.innerHTML = `<a href="${data.meetingLink}" target="_blank" rel="noopener noreferrer">${data.meetingLink}</a>`;
          viewMeetingLinkLabel.style.display = '';
          viewMeetingLinkValueArea.style.display = '';
        } else {
          viewEventMeetingLink.innerHTML = 'N/A';
          viewMeetingLinkLabel.style.display = 'none';
          viewMeetingLinkValueArea.style.display = 'none';
        }

        // Attendee Count and List Population
        const attendeeListElement = viewEventAttendeesList; // Direct reference
        const attendeeCountElement = viewEventAttendeesCount;
        const attendeeToggleButton = viewEventAttendeesList.closest('dd').querySelector('.btn-show-attendees');
        const attendeeCollapseElement = document.getElementById('viewEventAttendeesListCollapse');
        const collapseInstance = bootstrap.Collapse.getInstance(attendeeCollapseElement);

        attendeeListElement.innerHTML = '<li class="text-muted">None</li>';
        attendeeCountElement.textContent = '(0)';
        if (attendeeToggleButton) attendeeToggleButton.style.display = 'none';
        if (collapseInstance) { collapseInstance.hide(); } else if (attendeeCollapseElement) { attendeeCollapseElement.classList.remove('show'); }

        const attendeeCount = data.attendeeIds ? data.attendeeIds.length : 0;
        attendeeCountElement.textContent = `(${attendeeCount})`;

        if (attendeeCount > 0 && data.attendeesHtml && data.attendeesHtml.length > 0) {
          console.log(data.attendeesHtml);
          attendeeListElement.innerHTML = data.attendeesHtml.map(html => `<li class="mb-2">${html}</li>`).join('');
          if (attendeeToggleButton) attendeeToggleButton.style.display = 'inline-block';
        }

        // Set ID for Edit button
        editEventBtnView.setAttribute('data-event-id', data.id);
        viewEventModal.show();
      },
      error: jqXHR => { console.error("Error fetching details:", jqXHR); showNotification('error', 'Failed to load details.'); }
    });
  }

  editEventBtnView.addEventListener('click', function() {
    const eventId = this.getAttribute('data-event-id');
    if (eventId) { viewEventModal.hide(); openEditModal(eventId); }
  });

  function openEditModal(eventId) {
    const detailUrl = calendarUrls.details.replace(':id', eventId);
    resetEditModal();
    eventModalLabel.textContent = 'Edit Event';
    saveEventBtn.textContent = 'Update Event';
    deleteEventBtn.classList.remove('d-none');

    $.ajax({ url: detailUrl, method: 'GET',
      success: data => {
      console.log(data);
        eventIdInput.value = data.id;
        eventTitleInput.value = data.eventTitle || '';
        eventTypeInput.value = data.eventType || ''; // Set event type select
        const jqEventTypeSelect = $('#event_type');
        if (jqEventTypeSelect.length) {
          jqEventTypeSelect.val(data.eventType || '').trigger('change'); // Set event type select2
        }
        flatpickrStart?.setDate(data.eventStart || '', true);
        flatpickrEnd?.setDate(data.eventEnd || '', true);
        allDayCheckbox.checked = !!data.allDay;
        toggleEndTimeVisibility(!allDayCheckbox.checked); // Update end time visibility
        locationInput.value = data.eventLocation || '';
        meetingLinkInput.value = data.meetingLink || ''; // Set meeting link
        descriptionInput.value = data.eventDescription || '';
        attendeesSelect.val(data.attendeeIds || []).trigger('change');

        // Pre-populate Client Select2 if client data exists
        if (data.clientId && data.clientName) {
          // Create the option element
          var option = new Option(data.clientName, data.clientId, true, true);
          // Append it to the select
          clientIdSelect.append(option).trigger('change');
          // Manually trigger the Select2 event handling logic to ensure the option is set correctly
          clientIdSelect.trigger({ type: 'select2:select', params: { data: { id: data.clientId, text: data.clientName } } });
          clientSelectionArea.style.display = ''; // Show client area
          if (clientRequiredIndicator) clientRequiredIndicator.style.display = (data.eventType === 'Client Appointment' ? '' : 'none');
        } else {
          clientIdSelect.val(null).trigger('change'); // Clear if no client
          clientSelectionArea.style.display = 'none'; // Hide if no client
          if (clientRequiredIndicator) clientRequiredIndicator.style.display = 'none';
        }

        const colorToSelect = data.color || "";
        const colorRadio = eventForm.querySelector(`input[name="color"][value="${colorToSelect}"]`);
        if (colorRadio)
        {
          colorRadio.checked = true;
        } else
        {
          const defaultRadio = eventForm.querySelector('input[name="color"][value=""]');
          if(defaultRadio) defaultRadio.checked = true;
        }
        eventModal.show();
      },
      error: jqXHR => { console.error("Error fetching for edit:", jqXHR); showNotification('error', 'Failed data load for edit.'); }
    });
  }

  function handleEventDropResize(info) {
    const event = info.event; const eventId = event.id; if (!eventId) { info.revert(); return; }
    const updateUrl = calendarUrls.update.replace(':id', eventId);
    const eventData = {
      _method: 'PUT', event_title: event.title,
      event_start: moment(event.start).format('YYYY-MM-DD HH:mm:ss'),
      event_end: event.end ? moment(event.end).format('YYYY-MM-DD HH:mm:ss') : null,
      all_day: event.allDay ? 1 : 0, event_type: event.extendedProps?.eventType || 'Other',
      attendee_ids: event.extendedProps?.attendeeIds || [], color: event.backgroundColor || null
    };
    $.ajax({ url: updateUrl, method: 'POST', data: eventData,
      success: response => showNotification('success', response.message || 'Event updated.'),
      error: jqXHR => { console.error("Error on drop/resize:", jqXHR); showNotification('error', 'Update failed.'); info.revert(); }
    });
  }

  // Show/Hide Client selector based on Event Type
  $('#event_type').on('change', function() {
    console.log('Event change');
    const isClientAppointment = this.value === 'Client Appointment'; // Match Enum value
    clientSelectionArea.style.display = isClientAppointment ? '' : 'none';
    if (clientRequiredIndicator) clientRequiredIndicator.style.display = isClientAppointment ? '' : 'none';
    if (!isClientAppointment) {
      clientIdSelect.val(null).trigger('change'); // Clear client if type changes
    }
  });

  // Show/Hide End Time based on All Day checkbox
  allDayCheckbox?.addEventListener('change', function() {
    toggleEndTimeVisibility(!this.checked);
  });
  function toggleEndTimeVisibility(show) {
    const endContainer = eventEndInput?.closest('.col-md-6'); // Find parent container
    if (endContainer) {
      endContainer.style.display = show ? '' : 'none';
      if (!show) { flatpickrEnd?.clear(); } // Clear end date if hiding
    }
  }

  // --- Modal Form Handling ---

  saveEventBtn.addEventListener('click', function() {
    clearValidationErrors(); if (!validateEditForm()) { showNotification('error', 'Check form errors.'); return; }
    setLoadingButtonState(saveEventBtn, true);
    const isUpdate = !!eventIdInput.value; const eventId = eventIdInput.value;
    const url = isUpdate ? calendarUrls.update.replace(':id', eventId) : calendarUrls.store;
    const method = 'POST'; const selectedColorRadio = eventForm.querySelector('input[name="color"]:checked');
    const colorValue = selectedColorRadio ? selectedColorRadio.value : null;
    const payload = {
      event_title: eventTitleInput.value.trim(),
      event_type: eventTypeInput.value,
      event_start: flatpickrStart.selectedDates[0] ? moment(flatpickrStart.selectedDates[0]).format('YYYY-MM-DD HH:mm:ss') : null,
      event_end: (!allDayCheckbox.checked && flatpickrEnd.selectedDates[0]) ? moment(flatpickrEnd.selectedDates[0]).format('YYYY-MM-DD HH:mm:ss') : null, // Only send end if not all day
      all_day: allDayCheckbox.checked ? 1 : 0,
      attendee_ids: attendeesSelect.val() || [],
      event_location: locationInput.value.trim(),
      event_description: descriptionInput.value.trim(),
      meeting_link: meetingLinkInput.value.trim(), // Add meeting link
      client_id: clientIdSelect.val() || null, // Add client ID
      color: colorValue === "" ? null : colorValue, // Handle color
      _token: csrfToken // Send CSRF if not using jQuery ajaxSetup globally
    };
    if (isUpdate) { payload._method = 'PUT'; }
    $.ajax({ url: url, method: method, data: payload,
      success: response => { eventModal.hide(); showNotification('success', response.message); calendar.refetchEvents(); },
      error: jqXHR => {
        if (jqXHR.status === 422 && jqXHR.responseJSON?.errors) { displayValidationErrors(jqXHR.responseJSON.errors); showNotification('error', 'Validation errors.'); }
        else { showNotification('error', jqXHR.responseJSON?.message || 'Save failed.'); }
      },
      complete: () => setLoadingButtonState(saveEventBtn, false, isUpdate ? 'Update Event' : 'Add Event')
    });
  });

  deleteEventBtn.addEventListener('click', function() {
    const eventId = eventIdInput.value; if (!eventId) return;
    Swal.fire({ title: 'Are you sure?', text: "Action cannot be undone.", icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, delete it!' })
      .then((result) => {
        if (result.isConfirmed) {
          const deleteUrl = calendarUrls.destroy.replace(':id', eventId);
          setLoadingButtonState(deleteEventBtn, true);
          $.ajax({ url: deleteUrl, method: 'POST', data: { _method: 'POST' },
            success: response => { eventModal.hide(); showNotification('success', response.message); calendar.refetchEvents(); },
            error: jqXHR => { showNotification('error', jqXHR.responseJSON?.message || 'Delete failed.'); },
            complete: () => setLoadingButtonState(deleteEventBtn, false, 'Delete Event')
          });
        }
      });
  });

  // --- Utility Functions ---

  function resetEditModal() {
    eventForm.reset(); eventIdInput.value = '';
    flatpickrStart?.clear(); flatpickrEnd?.clear();
    attendeesSelect.val(null).trigger('change');
    clientIdSelect.val(null).trigger('change'); // Clear client select
    eventTypeInput.value = ""; // Reset event type select
    meetingLinkInput.value = ''; // Clear meeting link
    clientSelectionArea.style.display = 'none'; // Hide client area
    if (clientRequiredIndicator) clientRequiredIndicator.style.display = 'none';
    clearValidationErrors();
    eventForm.querySelector('input[name="color"][value=""]')?.setAttribute('checked', true); // Reset color
    eventModalLabel.textContent = 'Add Event'; saveEventBtn.textContent = 'Add Event';
    deleteEventBtn.classList.add('d-none');
    setLoadingButtonState(saveEventBtn, false, 'Add Event'); setLoadingButtonState(deleteEventBtn, false, 'Delete Event');
    toggleEndTimeVisibility(true); // Show end time by default on reset
  }

  function showNotification(icon, title) {
    showSuccessToast(title);
  }

 /*  function showNotification(icon, title) { Swal.fire({ icon, title, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true, didOpen: t => { t.addEventListener('mouseenter', Swal.stopTimer); t.addEventListener('mouseleave', Swal.resumeTimer); } }); }
 */
  function validateEditForm() {
    let isValid = true; clearValidationErrors();
    if (!eventTitleInput.value.trim()) { displayValidationErrors({'event_title': ['Title is required.']}); isValid = false; }
    if (!eventTypeInput.value) { displayValidationErrors({'event_type': ['Event type is required.']}); isValid = false; }
    if (!flatpickrStart || !flatpickrStart.selectedDates.length) { displayValidationErrors({'event_start': ['Start date is required.']}); isValid = false; }
    if (flatpickrStart?.selectedDates.length && flatpickrEnd?.selectedDates.length && flatpickrEnd.selectedDates[0] < flatpickrStart.selectedDates[0]) { displayValidationErrors({'event_end': ['End date must be after start.']}); isValid = false; }
    // --- End Date (if not all day) ---
    const isAllDay = allDayCheckbox.checked;
    if (!isAllDay && flatpickrStart?.selectedDates.length && flatpickrEnd?.selectedDates.length && flatpickrEnd.selectedDates[0] < flatpickrStart.selectedDates[0]) {
      displayValidationErrors({'event_end': ['End date must be after start date.']}); isValid = false;
    }
    // --- Client (if required) ---
    const isClientAppointment = eventTypeInput.value === 'Client Appointment';
    if (isClientAppointment && !clientIdSelect.val()) {
      displayValidationErrors({'client_id': ['Client is required for this event type.']}); isValid = false;
    }
    // --- Meeting Link (URL format) ---
    if (meetingLinkInput.value.trim() && !isValidHttpUrl(meetingLinkInput.value.trim())) {
      displayValidationErrors({'meeting_link': ['Please enter a valid URL (e.g., https://...).']}); isValid = false;
    }
    return isValid;
  }

  function displayValidationErrors(errors) {
    clearValidationErrors();
    for (const fieldName in errors) {
      const inputElement = eventForm.querySelector(`[name="${fieldName}"], #${fieldName}, [name="${fieldName}[]"]`); // Handle array name for attendees
      let isSelect2 = $(inputElement).hasClass('select2-hidden-accessible'); // Check if it's a Select2
      let targetElement = isSelect2 ? $(inputElement).siblings('.select2-container') : $(inputElement); // Target container for Select2
      let feedbackElement = targetElement.closest('.mb-3').find('.invalid-feedback'); // Find feedback within parent group

      if (targetElement.length) {
        targetElement.addClass('is-invalid'); // Add invalid class to input or select2 container
        if(feedbackElement.length) {
          feedbackElement.text(errors[fieldName][0]).show(); // Show feedback message
        }
      }
    }
    // Focus first error
    const firstInvalid = $(eventForm).find('.is-invalid').first();
    if (firstInvalid.hasClass('select2-container')) {
      firstInvalid.prev('select').select2('open'); // Open Select2 if it's the first error
    } else {
      firstInvalid.focus();
    }
  }
  function isValidHttpUrl(string) { let url; try { url = new URL(string); } catch (_) { return false; } return url.protocol === "http:" || url.protocol === "https:"; }

  function clearValidationErrors() {
    eventForm.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    eventForm.querySelectorAll('.invalid-feedback').forEach(el => { el.textContent = ''; el.style.display = 'none'; }); // Clear and hide
    // Specifically clear Select2 invalid state
    $(eventForm).find('.select2-container.is-invalid').removeClass('is-invalid');
  }

  function setLoadingButtonState(button, isLoading, defaultText = '') {
    if (!button) return; button.disabled = isLoading;
    button.innerHTML = isLoading ? '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...' : (defaultText || button.textContent);
  }

  eventModalElement.addEventListener('hidden.bs.modal', () => resetEditModal());

}); // End DOMContentLoaded
