$(document).ready(function () {
    const baseUrl = ''; 
    const addNewRetailerForm = document.getElementById('createRetailerForm');

    const fv = FormValidation.formValidation(addNewRetailerForm, {
        fields: {
            name: {
                validators: {
                    notEmpty: {
                        message: 'The name is required'
                    }
                }
            },
            
            address: {
                validators: {
                    notEmpty: {
                        message: 'The address is required'
                    }
                }
            },
            
            contactPerson: {
                validators: {
                    notEmpty: {
                        message: 'The contact person is required'
                    }
                }
            },
            phoneNumber: {
                validators: {
                    notEmpty: {
                        message: 'The phone number is required'
                    },
                    
                }
            },
            email: {
                validators: {
                    emailAddress: {
                        message: 'The input is not a valid email address'
                    }
                }
            },
            
        },
        plugins: {
            trigger: new FormValidation.plugins.Trigger(),
            bootstrap5: new FormValidation.plugins.Bootstrap5({
                eleValidClass: '',
                rowSelector: function (field, ele) {
                    return '.mb-3'; 
                }
            }),
            submitButton: new FormValidation.plugins.SubmitButton(),
            autoFocus: new FormValidation.plugins.AutoFocus()
        }
    }).on('core.form.valid', function () {
       $('#createRetailerForm').submit();
      });





   
   
});
