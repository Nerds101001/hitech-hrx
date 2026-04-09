'use strict'

$(function () {

  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });

  // Get the backup list when the page loads
  getBackupList();
});

window.getBackupList = function () {

  //Show loader in the backup list div
  $('#backupListDiv').html('<div class="d-flex justify-content-center mt-5"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');

  //Get Backup lists
  $.ajax({
    url: `${baseUrl}utilities/getBackupList`,
    type: 'GET',
    success: function (response) {
      console.log(response);
      const backupListDiv = $('#backupListDiv');
      if (response && response.status === 'success') {
        const backupList = response.data;
        var html = '';
        if (backupList && backupList.length > 0) {

          backupList.forEach(backup => {

              html += ` <div class="col-12 col-md-6 col-lg-4 mt-3">
          <div class="card h-100 shadow-sm">
            <div class="card-body">
              <h5 class="card-title"><i class="bx bx-archive"></i> ${backup.file_name}</h5>
              <p class="card-text"><i class="bx bx-hdd"></i>  ${backup.file_size}</p>
              <p class="card-text"><i class="bx bx-time-five"></i> ${backup.last_modified}</p>
              <div class="d-flex justify-content-between">
                <a href="${baseUrl}utilities/downloadBackup/${backup.file_name}" class="btn btn-primary flex-grow-1 me-2">Download</a>
                <button type="button" class="btn btn-success btn-icon me-2" title="Restore Backup" onclick="confirmRestore('${backup.file_name}')">
                  <i class="bx bx-reset"></i>
                </button>
                <button type="button" class="btn btn-danger btn-icon" title="Delete Backup" onclick="confirmDelete('${backup.file_name}')">
                  <i class="bx bx-trash"></i>
                </button>
              </div>
            </div>
          </div>
        </div>`;

            }
          );
        } else {
          html = '<div class="card-body text-center">\n' +
            '    <div class="alert alert-light" role="alert">\n' +
            '      <i class="bx bx-folder-open text-muted" style="font-size: 3rem;"></i>\n' +
            '      <h5 class="mt-3 text-danger">No backups found.</h5>\n' +
            '      <p class="text-muted">It looks like you haven\'t created any backups yet. Click the button below to create your first backup.</p>\n' +
            '      <a href="" onclick="createBackup()" class="btn btn-primary mt-3">\n' +
            '        <i class="bx bx-cloud-upload me-2"></i> Create First Backup\n' +
            '      </a>\n' +
            '    </div>\n' +
            '  </div>';
        }
        backupListDiv.html(html);
      } else {
        backupListDiv.html('<div class="alert alert-danger">There was an issue fetching the backup list. Please try again.</div>');
      }
    },
    error: function (response) {
      console.log(response);
    }
  });

}

window.confirmDelete = function (fileName) {
  var csrfToken = $('meta[name="csrf-token"]').attr('content');
  Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    confirmButtonText: 'Yes, delete it!',
    customClass: {
      confirmButton: 'btn btn-primary me-3',
      cancelButton: 'btn btn-label-secondary'
    },
  }).then((result) => {
    if (result.isConfirmed) {
      // Create a hidden form and submit it to delete the file
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = `${baseUrl}utilities/deleteBackup/${fileName}`;

      // Add CSRF token input
      var csrfInput = document.createElement('input');
      csrfInput.type = 'hidden';
      csrfInput.name = '_token';
      csrfInput.value = csrfToken;
      form.appendChild(csrfInput);

      // Add the DELETE method input
      var methodInput = document.createElement('input');
      methodInput.type = 'hidden';
      methodInput.name = '_method';
      methodInput.value = 'DELETE';
      form.appendChild(methodInput);

      document.body.appendChild(form);
      form.submit();
    }
  });
}

window.createBackup = function () {
  var csrfToken = $('meta[name="csrf-token"]').attr('content');
  Swal.fire({
    title: 'Creating Backup...',
    text: 'Please wait while your backup is being created.',
    icon: 'info',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false, // Hide the confirm button
    showCancelButton: false,  // Hide the cancel button
    showDenyButton: false,
    customClass: {},
    willOpen: () => {
      Swal.showLoading();
    }
  });

  // Simulate an AJAX request to create the backup
  $.ajax({
    url: `${baseUrl}utilities/createBackup`,
    type: 'POST',
    data: {
      _token: csrfToken
    },
    success: function (response) {
      Swal.fire({
        title: 'Success!',
        text: 'Backup has been created successfully.',
        customClass: {
          confirmButton: 'btn btn-success'
        }
      }).then(() => {
        // Reload the page or refresh the backup list
        location.reload();
      });
    },
    error: function (error) {
      Swal.fire({
        title: 'Error!',
        text: 'There was an issue creating the backup. Please try again.',
        icon: 'error',
        confirmButtonText: 'OK'
      });
    }
  });
}

window.confirmRestore = function (fileName) {
  Swal.fire({
    title: 'Are you sure?',
    text: "This will restore the " + fileName + " file.",
    icon: 'warning',
    customClass: {
      confirmButton: 'btn btn-primary me-3',
      cancelButton: 'btn btn-label-secondary'
    },
    confirmButtonText: "Yes, restore it!",
    showCancelButton: true,
    cancelButtonText: 'Cancel',
  }).then((result) => {
    if (result.isConfirmed) {
      showRestoringLoader();

      // Simulate an AJAX request to restore the backup
      $.ajax({
        url: `${baseUrl}utilities/restoreBackup/${fileName}`,
        type: 'POST',
        success: function (response) {
          console.log(response);
          // Simulate progress update (replace with real updates if available)
          let progress = 0;
          let interval = setInterval(() => {
            progress += 20; // Increase progress by 20% each time
            updateRestoreProgress(progress, `Restoring ${fileName}...`);

            if (progress >= 100) {
              clearInterval(interval);
              Swal.fire({
                title: 'Success!',
                text: 'Backup has been restored successfully.',
                icon: 'success',
                confirmButtonText: 'OK',
                customClass: {
                  confirmButton: 'btn btn-success'
                }
              });
            }
          }, 1000); // Update every second
        },
        error: function (error) {
          console.log(error);
          Swal.fire({
            title: 'Error!',
            text: 'There was an issue restoring the backup. Please try again.',
            icon: 'error',
            customClass: {
              confirmButton: 'btn btn-danger'
            },
            confirmButtonText: 'OK'
          });
        }
      });
    }
  });
}


window.showRestoringLoader = function (initialText = 'Initializing...') {
  Swal.fire({
    title: 'Restoring Backup...',
    html: `
      <p>Please wait while your backup is being restored.</p>
      <p class="text-warning">Warning: This action will overwrite existing data.</p>
      <p class="text-warning">Do not close this window or navigate away during the restore process.</p>
      <div class="progress mt-3" style="height: 25px;">
        <div id="progress-bar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
      </div>
      <p id="current-process" class="mt-3">${initialText}</p>
    `,
    icon: 'info',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false, // Hide the confirm button
    showCancelButton: false,  // Hide the cancel button
    showDenyButton: false,    // Hide the deny button
    customClass: {},
    willOpen: () => {
      Swal.showLoading();
    }
  });
}


// Function to update progress bar and process text
window.updateRestoreProgress = function (percentage, processText) {
  const progressBar = document.getElementById('progress-bar');
  const processTextElement = document.getElementById('current-process');

  progressBar.style.width = percentage + '%';
  progressBar.setAttribute('aria-valuenow', percentage);
  progressBar.innerText = percentage + '%';

  processTextElement.innerText = processText;
}


