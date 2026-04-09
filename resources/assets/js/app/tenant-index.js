'use strict';
$(function () {
  console.log('Tenant Index JS Loaded');

  const createTenantForm = document.getElementById('createTenantForm');

  const fv = FormValidation.formValidation(createTenantForm, {
    fields: {
      name: {
        validators: {
          notEmpty: {
            message: 'The name is required'
          }
        }
      },
      domain: {
        validators: {
          notEmpty: {
            message: 'The domain is required'
          },
          regexp: {
            regexp: /^[a-zA-Z0-9]+$/,
            message: 'The domain can only consist of alphabetical and number'
          }
        }
      },
      companyName: {
        validators: {
          notEmpty: {
            message: 'The company name is required'
          }
        }
      },
      emailDomain: {
        validators: {
          notEmpty: {
            message: 'The email domain is required'
          },
          url: {
            message: 'The value is not a valid URL'
          }
        }
      }
    },
    plugins: {
      trigger: new FormValidation.plugins.Trigger(),
      bootstrap5: new FormValidation.plugins.Bootstrap5({
        eleValidClass: '',
        rowSelector: '.col-12'
      }),
      autoFocus: new FormValidation.plugins.AutoFocus(),
      submitButton: new FormValidation.plugins.SubmitButton(),
      declarative: new FormValidation.plugins.Declarative({})
    }
  }).on('core.form.valid', function () {
    console.log('Form Valid');
    createTenantForm.submit();
  });
});
