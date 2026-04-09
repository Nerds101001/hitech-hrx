/**
 *  Form Wizard
 */

'use strict';

(function () {
  const select2 = $('.select2'),
    selectPicker = $('.selectpicker');

  // Wizard Validation
  // --------------------------------------------------------------------
  const wizardValidation = document.querySelector('#wizard-validation');
  if (typeof wizardValidation !== undefined && wizardValidation !== null) {
    // Wizard form
    const wizardValidationForm = wizardValidation.querySelector('#wizard-validation-form');
    // Wizard steps
    const wizardValidationFormStep1 = wizardValidationForm.querySelector('#personal-details-validation');
    const wizardValidationFormStep2 = wizardValidationForm.querySelector('#employee-info-validation');
    const wizardValidationFormStep3 = wizardValidationForm.querySelector('#salary-validation');
    // Wizard next prev button
    const wizardValidationNext = [].slice.call(wizardValidationForm.querySelectorAll('.btn-next'));
    const wizardValidationPrev = [].slice.call(wizardValidationForm.querySelectorAll('.btn-prev'));

    const validationStepper = new Stepper(wizardValidation, {
      linear: true
    });

    // Personal info
    const FormValidation1 = FormValidation.formValidation(wizardValidationFormStep1, {
      fields: {
        file: {
          validators: {
            file: {
              extension: 'jpeg,jpg,png',
              type: 'image/jpeg,image/png',
              maxSize: 5 * 1024 * 1024, // 5 MB
              message: 'The selected file is not valid, only allowed jpeg, jpg, png files and 5 MB size'
            }
          }
        },
        firstName: {
          validators: {
            notEmpty: {
              message: 'The First name is required'
            },
            stringLength: {
              min: 2,
              max: 30,
              message: 'The name must be more than 3 and less than 30 characters long'
            },
            regexp: {
              regexp: /^[a-zA-Z0-9 ]+$/,
              message: 'The name can only consist of alphabetical, number and space'
            }
          }
        },
        lastName: {
          validators: {
            notEmpty: {
              message: 'The last name is required'
            }
          }
        },
        email: {
          validators: {
            notEmpty: {
              message: 'The Email is required'
            },
            emailAddress: {
              message: 'The value is not a valid email address'
            },
            remote: {
              url: `${baseUrl}employees/checkEmailValidationAjax`,
              message: 'The email is already taken',
              method: 'GET'
            }
          }
        },
        phone: {
          validators: {
            notEmpty: {
              message: 'The Phone is required'
            },
            stringLength: {
              min: 1,
              max: 15,
              message: 'The Phone must be 15 characters long'
            },
            remote: {
              url: `${baseUrl}employees/checkPhoneValidationAjax`,
              message: 'The phone is already taken',
              method: 'GET'
            }
          }
        },
        altPhone: {
          validators: {
            stringLength: {
              min: 1,
              max: 15,
              message: 'The Phone must be 15 characters long'
            }
          }
        },
        gender: {
          validators: {
            notEmpty: {
              message: 'Please choose'
            }
          }
        },
        dob: {
          validators: {
            notEmpty: {
              message: 'The Date of Birth is required'
            }
          }
        }
      },
      plugins: {
        trigger: new FormValidation.plugins.Trigger(),
        bootstrap5: new FormValidation.plugins.Bootstrap5({
          eleValidClass: '',
          rowSelector: '.col-sm-6'
        }),
        autoFocus: new FormValidation.plugins.AutoFocus(),
        submitButton: new FormValidation.plugins.SubmitButton(),
        declarative: new FormValidation.plugins.Declarative({})
      },
      init: instance => {
        instance.on('plugins.message.placed', function (e) {
          //* Move the error message out of the `input-group` element
          if (e.element.parentElement.classList.contains('input-group')) {
            e.element.parentElement.insertAdjacentElement('afterend', e.messageElement);
          }
        });
      }
    }).on('core.form.valid', function () {
      // Jump to the next step when all fields in the current step are valid
      validationStepper.next();
    });

    // Employment details
    const FormValidation2 = FormValidation.formValidation(wizardValidationFormStep2, {
      fields: {
        code: {
          validators: {
            notEmpty: {
              message: 'The Employee Code is required'
            },
            remote: {
              url: `${baseUrl}employees/checkEmployeeCodeValidationAjax`,
              message: 'The code is already taken',
              method: 'GET'
            }
          }
        },
        designationId: {
          validators: {
            notEmpty: {
              message: 'The Designation is required'
            }
          }
        },
        doj: {
          validators: {
            notEmpty: {
              message: 'The Date of Joining is required'
            }
          }
        },
        teamId: {
          validators: {
            notEmpty: {
              message: 'Please choose a team'
            }
          }
        },
        shiftId: {
          validators: {
            notEmpty: {
              message: 'Please choose a shift'
            }
          }
        },
        attendanceType: {
          validators: {
            notEmpty: {
              message: 'Please choose a attendance type'
            }
          }
        },
        reportingToId: {
          validators: {
            notEmpty: {
              message: 'Please choose a reporting to'
            }
          }
        }
      },
      plugins: {
        trigger: new FormValidation.plugins.Trigger(),
        bootstrap5: new FormValidation.plugins.Bootstrap5({
          eleValidClass: '',
          rowSelector: '.col-sm-6'
        }),
        autoFocus: new FormValidation.plugins.AutoFocus(),
        submitButton: new FormValidation.plugins.SubmitButton()
      }
    }).on('core.form.valid', function () {
      validationStepper.next();
    });

    // Salary & Compensation
    const FormValidation3 = FormValidation.formValidation(wizardValidationFormStep3, {
      fields: {
        baseSalary: {
          validators: {
            notEmpty: {
              message: 'The Base Salary is required'
            }
          }
        }
      },
      plugins: {
        trigger: new FormValidation.plugins.Trigger(),
        bootstrap5: new FormValidation.plugins.Bootstrap5({
          eleValidClass: '',
          rowSelector: '.col-sm-6'
        }),
        autoFocus: new FormValidation.plugins.AutoFocus(),
        submitButton: new FormValidation.plugins.SubmitButton()
      }
    }).on('core.form.valid', function () {
      wizardValidationForm.submit();
    });

    wizardValidationNext.forEach(item => {
      item.addEventListener('click', event => {
        switch (validationStepper._currentIndex) {
          case 0:
            FormValidation1.validate();
            break;

          case 1:
            FormValidation2.validate();
            break;

          case 2:
            FormValidation3.validate();
            break;

          default:
            break;
        }
      });
    });

    wizardValidationPrev.forEach(item => {
      item.addEventListener('click', event => {
        switch (validationStepper._currentIndex) {
          case 2:
            validationStepper.previous();
            break;

          case 1:
            validationStepper.previous();
            break;

          case 0:

          default:
            break;
        }
      });
    });

    // select2
    if (select2.length) {
      select2.each(function () {
        var $this = $(this);
        $this.wrap('<div class="position-relative"></div>');
        $this
          .select2({
            dropdownParent: $this.parent()
          })
          .on('change', function () {
            /* // Revalidate the color field when an option is chosen
             FormValidation2.revalidateField('formValidationCountry');*/
          });
      });
    }
  }
})();
