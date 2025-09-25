/**
 * Form Validation JavaScript
 * Client-side validation for public forms
 * Requires: browser-compatibility.js
 */

// Use function constructor for IE compatibility
function FormValidator(formId, fields) {
    this.form = document.getElementById(formId);
    this.fields = fields;
    this.validators = {};
    
    this.initializeValidation();
    this.attachEventListeners();
}

FormValidator.prototype = {
    initializeValidation: function() {
        // Set up validators for each field type
        this.validators = {
            text: this.validateText.bind(this),
            email: this.validateEmail.bind(this),
            number: this.validateNumber.bind(this),
            aadhar: this.validateAadhar.bind(this),
            select: this.validateSelect.bind(this),
            radio: this.validateRadio.bind(this),
            checkbox: this.validateCheckbox.bind(this),
            file: this.validateFile.bind(this),
            photo: this.validatePhoto.bind(this),
            signature: this.validateSignature.bind(this)
        };
    }
    
    attachEventListeners: function() {
        if (!this.form) return;
        
        var self = this;
        
        // Form submission
        this.form.addEventListener('submit', function(e) {
            self.handleSubmit(e);
        });
        
        // Real-time validation
        this.fields.forEach(function(field) {
            var fieldElement = document.getElementById('field_' + field.id);
            if (fieldElement) {
                fieldElement.addEventListener('blur', function() {
                    self.validateField(field);
                });
                fieldElement.addEventListener('input', function() {
                    self.clearFieldError(field.id);
                });
                
                // Special handling for file inputs
                if (['file', 'photo', 'signature'].includes(field.type)) {
                    fieldElement.addEventListener('change', (e) => {
                        this.handleFileUpload(e, field);
                    });
                }
                
                // Special handling for Aadhar formatting
                if (field.type === 'aadhar') {
                    fieldElement.addEventListener('input', this.formatAadharInput.bind(this));
                    fieldElement.addEventListener('keyup', (e) => {
                        // Provide real-time feedback after a short delay
                        clearTimeout(fieldElement.validationTimeout);
                        fieldElement.validationTimeout = setTimeout(() => {
                            this.validateAadharRealtime(field, e.target);
                        }, 500);
                    });
                }
            }
        });
    }
    
    handleSubmit(e) {
        e.preventDefault();
        
        let isValid = true;
        
        // Validate all fields
        this.fields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
        
        if (isValid) {
            this.submitForm();
        } else {
            // Scroll to first error
            const firstError = document.querySelector('.field-error.show');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }
    
    validateField(field) {
        const validator = this.validators[field.type];
        if (validator) {
            return validator(field);
        }
        return true;
    }
    
    validateText(field) {
        const element = document.getElementById('field_' + field.id);
        const value = element.value.trim();
        
        if (field.required && !value) {
            this.showFieldError(field.id, 'This field is required.');
            return false;
        }
        
        if (field.maxLength && value.length > field.maxLength) {
            this.showFieldError(field.id, `Maximum length is ${field.maxLength} characters.`);
            return false;
        }
        
        this.clearFieldError(field.id);
        return true;
    }
    
    validateEmail(field) {
        const element = document.getElementById('field_' + field.id);
        const value = element.value.trim();
        
        if (field.required && !value) {
            this.showFieldError(field.id, 'This field is required.');
            return false;
        }
        
        if (value && !this.isValidEmail(value)) {
            this.showFieldError(field.id, 'Please enter a valid email address.');
            return false;
        }
        
        this.clearFieldError(field.id);
        return true;
    }
    
    validateNumber(field) {
        const element = document.getElementById('field_' + field.id);
        const value = element.value.trim();
        
        if (field.required && !value) {
            this.showFieldError(field.id, 'This field is required.');
            return false;
        }
        
        if (value) {
            const numValue = parseFloat(value);
            
            if (isNaN(numValue)) {
                this.showFieldError(field.id, 'Please enter a valid number.');
                return false;
            }
            
            if (field.min !== null && numValue < field.min) {
                this.showFieldError(field.id, `Minimum value is ${field.min}.`);
                return false;
            }
            
            if (field.max !== null && numValue > field.max) {
                this.showFieldError(field.id, `Maximum value is ${field.max}.`);
                return false;
            }
        }
        
        this.clearFieldError(field.id);
        return true;
    }
    
    validateAadhar(field) {
        const element = document.getElementById('field_' + field.id);
        const value = element.value.replace(/\s/g, ''); // Remove spaces
        
        if (field.required && !value) {
            this.showFieldError(field.id, 'This field is required.');
            return false;
        }
        
        if (value) {
            if (value.length !== 12) {
                this.showFieldError(field.id, 'Aadhar number must be exactly 12 digits.');
                return false;
            }
            
            if (!/^\d{12}$/.test(value)) {
                this.showFieldError(field.id, 'Aadhar number must contain only digits.');
                return false;
            }
            
            if (/^(\d)\1{11}$/.test(value)) {
                this.showFieldError(field.id, 'Aadhar number cannot have all same digits.');
                return false;
            }
            
            if (value.charAt(0) === '0' || value.charAt(0) === '1') {
                this.showFieldError(field.id, 'Aadhar number cannot start with 0 or 1.');
                return false;
            }
        }
        
        this.clearFieldError(field.id);
        return true;
    }
    
    validateSelect(field) {
        const element = document.getElementById('field_' + field.id);
        const value = element.value;
        
        if (field.required && !value) {
            this.showFieldError(field.id, 'Please select an option.');
            return false;
        }
        
        this.clearFieldError(field.id);
        return true;
    }
    
    validateRadio(field) {
        const elements = document.querySelectorAll(`input[name="${field.id}"]`);
        const checked = Array.from(elements).some(el => el.checked);
        
        if (field.required && !checked) {
            this.showFieldError(field.id, 'Please select an option.');
            return false;
        }
        
        this.clearFieldError(field.id);
        return true;
    }
    
    validateCheckbox(field) {
        const elements = document.querySelectorAll(`input[name="${field.id}[]"]`);
        const checked = Array.from(elements).some(el => el.checked);
        
        if (field.required && !checked) {
            this.showFieldError(field.id, 'Please select at least one option.');
            return false;
        }
        
        this.clearFieldError(field.id);
        return true;
    }
    
    validateFile(field) {
        const element = document.getElementById('field_' + field.id);
        const file = element.files[0];
        
        if (field.required && !file) {
            this.showFieldError(field.id, 'Please select a file.');
            return false;
        }
        
        if (file) {
            // Check file size
            const maxSize = (field.maxSize || 5) * 1024 * 1024; // Convert MB to bytes
            if (file.size > maxSize) {
                this.showFieldError(field.id, `File size must be less than ${field.maxSize || 5}MB.`);
                return false;
            }
            
            // Check file type
            if (field.allowedTypes && field.allowedTypes.length > 0) {
                const fileExtension = file.name.split('.').pop().toLowerCase();
                if (!field.allowedTypes.includes(fileExtension)) {
                    this.showFieldError(field.id, `Allowed file types: ${field.allowedTypes.join(', ')}`);
                    return false;
                }
            }
        }
        
        this.clearFieldError(field.id);
        return true;
    }
    
    validatePhoto(field) {
        const element = document.getElementById('field_' + field.id);
        const file = element.files[0];
        
        if (field.required && !file) {
            this.showFieldError(field.id, 'Please select a photo.');
            return false;
        }
        
        if (file) {
            // Check if it's an image
            if (!file.type.startsWith('image/')) {
                this.showFieldError(field.id, 'Please select a valid image file.');
                return false;
            }
            
            // Check file size (default 5MB for photos)
            const maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                this.showFieldError(field.id, 'Photo size must be less than 5MB.');
                return false;
            }
        }
        
        this.clearFieldError(field.id);
        return true;
    }
    
    validateSignature(field) {
        const element = document.getElementById('field_' + field.id);
        const file = element.files[0];
        
        if (field.required && !file) {
            this.showFieldError(field.id, 'Please upload your signature.');
            return false;
        }
        
        if (file) {
            // Check if it's an image
            if (!file.type.startsWith('image/')) {
                this.showFieldError(field.id, 'Please select a valid image file for signature.');
                return false;
            }
            
            // Check file size (default 2MB for signatures)
            const maxSize = 2 * 1024 * 1024;
            if (file.size > maxSize) {
                this.showFieldError(field.id, 'Signature file size must be less than 2MB.');
                return false;
            }
        }
        
        this.clearFieldError(field.id);
        return true;
    }
    
    showFieldError(fieldId, message) {
        const errorElement = document.getElementById('error-' + fieldId);
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.add('show');
        }
        
        const fieldElement = document.getElementById('field_' + fieldId);
        if (fieldElement) {
            fieldElement.classList.add('error');
            fieldElement.setAttribute('aria-invalid', 'true');
            fieldElement.setAttribute('aria-describedby', 'error-' + fieldId);
        }
        
        // Add error styling to form group
        const formGroup = fieldElement?.closest('.form-group');
        if (formGroup) {
            formGroup.classList.add('has-error');
        }
    }
    
    clearFieldError(fieldId) {
        const errorElement = document.getElementById('error-' + fieldId);
        if (errorElement) {
            errorElement.classList.remove('show');
        }
        
        const fieldElement = document.getElementById('field_' + fieldId);
        if (fieldElement) {
            fieldElement.classList.remove('error');
            fieldElement.removeAttribute('aria-invalid');
            fieldElement.removeAttribute('aria-describedby');
        }
        
        // Remove error styling from form group
        const formGroup = fieldElement?.closest('.form-group');
        if (formGroup) {
            formGroup.classList.remove('has-error');
        }
    }
    
    handleFileUpload(event, field) {
        const file = event.target.files[0];
        if (!file) return;
        
        // Show preview for images
        if (['photo', 'signature'].includes(field.type) && file.type.startsWith('image/')) {
            this.showImagePreview(field.id, file);
        }
        
        // Validate the file
        this.validateField(field);
    }
    
    showImagePreview(fieldId, file) {
        const previewContainer = document.getElementById('preview-' + fieldId);
        if (!previewContainer) return;
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = previewContainer.querySelector('.preview-image');
            if (img) {
                img.src = e.target.result;
                previewContainer.style.display = 'flex';
            }
        };
        reader.readAsDataURL(file);
    }
    
    formatAadharInput(event) {
        let value = event.target.value.replace(/\D/g, ''); // Remove non-digits
        
        // Limit to 12 digits
        if (value.length > 12) {
            value = value.substring(0, 12);
        }
        
        // Format as XXXX XXXX XXXX
        if (value.length > 8) {
            value = value.substring(0, 4) + ' ' + value.substring(4, 8) + ' ' + value.substring(8);
        } else if (value.length > 4) {
            value = value.substring(0, 4) + ' ' + value.substring(4);
        }
        
        event.target.value = value;
        
        // Real-time validation feedback
        const fieldId = event.target.id.replace('field_', '');
        const field = this.fields.find(f => f.id === fieldId);
        if (field && field.type === 'aadhar') {
            if (value.replace(/\s/g, '').length === 12) {
                event.target.classList.remove('error');
                event.target.classList.add('valid');
                this.clearFieldError(fieldId);
            } else if (value.length > 0) {
                event.target.classList.remove('valid');
                if (value.replace(/\s/g, '').length < 12) {
                    // Don't show error while typing, only on blur
                    event.target.classList.remove('error');
                }
            }
        }
    }
    
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    validateAadharRealtime(field, element) {
        const value = element.value.replace(/\s/g, '');
        
        if (value.length === 0) {
            element.classList.remove('valid', 'error');
            this.clearFieldError(field.id);
            return;
        }
        
        if (value.length < 12) {
            element.classList.remove('valid');
            // Don't show error while still typing
            return;
        }
        
        if (this.isValidAadhar(element.value)) {
            element.classList.remove('error');
            element.classList.add('valid');
            this.clearFieldError(field.id);
        } else {
            element.classList.remove('valid');
            element.classList.add('error');
        }
    }
    
    isValidAadhar(aadhar) {
        // Remove spaces and check if it's exactly 12 digits
        const cleanAadhar = aadhar.replace(/\s/g, '');
        
        // Basic format check
        if (!/^\d{12}$/.test(cleanAadhar)) {
            return false;
        }
        
        // Additional validation: Aadhar numbers cannot be all same digits
        if (/^(\d)\1{11}$/.test(cleanAadhar)) {
            return false;
        }
        
        // Aadhar numbers cannot start with 0 or 1
        if (cleanAadhar.charAt(0) === '0' || cleanAadhar.charAt(0) === '1') {
            return false;
        }
        
        return true;
    }
    
    submitForm() {
        const submitBtn = this.form.querySelector('.btn-submit');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            submitBtn.textContent = 'Submitting...';
        }
        
        // Create FormData object
        const formData = new FormData(this.form);
        
        // Submit the form
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            // Replace the current page content with the response
            document.body.innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while submitting the form. Please try again.');
            
            // Re-enable submit button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
                submitBtn.textContent = submitBtn.dataset.originalText || 'Submit';
            }
        });
    }
}

// Utility functions for image cropping (will be implemented in task 6.2)
function openCropModal(fieldId) {
    alert('Image cropping functionality will be implemented in task 6.2');
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // FormValidator will be initialized by the inline script in the HTML
});