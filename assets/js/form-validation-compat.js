/**
 * Form Validation JavaScript - Cross-Browser Compatible Version
 * Client-side validation for public forms
 * Requires: browser-compatibility.js
 */

// Use function constructor for IE compatibility
function FormValidator(formId, fields) {
    this.form = document.getElementById(formId);
    this.fields = fields || [];
    this.validators = {};
    
    this.initializeValidation();
    this.attachEventListeners();
}

FormValidator.prototype = {
    initializeValidation: function() {
        var self = this;
        // Set up validators for each field type
        this.validators = {
            text: function(field) { return self.validateText(field); },
            email: function(field) { return self.validateEmail(field); },
            number: function(field) { return self.validateNumber(field); },
            aadhar: function(field) { return self.validateAadhar(field); },
            select: function(field) { return self.validateSelect(field); },
            radio: function(field) { return self.validateRadio(field); },
            checkbox: function(field) { return self.validateCheckbox(field); },
            file: function(field) { return self.validateFile(field); },
            photo: function(field) { return self.validatePhoto(field); },
            signature: function(field) { return self.validateSignature(field); }
        };
    },
    
    attachEventListeners: function() {
        if (!this.form) return;
        
        var self = this;
        
        // Form submission
        this.form.addEventListener('submit', function(e) {
            self.handleSubmit(e);
        });
        
        // Real-time validation
        for (var i = 0; i < this.fields.length; i++) {
            var field = this.fields[i];
            var fieldElement = document.getElementById('field_' + field.id);
            
            if (fieldElement) {
                // Use closure to capture field reference
                (function(currentField) {
                    fieldElement.addEventListener('blur', function() {
                        self.validateField(currentField);
                    });
                    
                    fieldElement.addEventListener('input', function() {
                        self.clearFieldError(currentField.id);
                    });
                    
                    // Special handling for file inputs
                    if (currentField.type === 'file' || currentField.type === 'photo' || currentField.type === 'signature') {
                        fieldElement.addEventListener('change', function(e) {
                            self.handleFileUpload(e, currentField);
                        });
                    }
                    
                    // Special handling for Aadhar formatting
                    if (currentField.type === 'aadhar') {
                        fieldElement.addEventListener('input', function(e) {
                            self.formatAadharInput(e);
                        });
                        
                        fieldElement.addEventListener('keyup', function(e) {
                            // Provide real-time feedback after a short delay
                            if (fieldElement.validationTimeout) {
                                clearTimeout(fieldElement.validationTimeout);
                            }
                            fieldElement.validationTimeout = setTimeout(function() {
                                self.validateAadharRealtime(currentField, e.target);
                            }, 500);
                        });
                    }
                })(field);
            }
        }
    },
    
    handleSubmit: function(e) {
        e.preventDefault();
        
        var isValid = true;
        
        // Validate all fields
        for (var i = 0; i < this.fields.length; i++) {
            if (!this.validateField(this.fields[i])) {
                isValid = false;
            }
        }
        
        if (isValid) {
            this.submitForm();
        } else {
            // Scroll to first error
            var firstError = document.querySelector('.field-error.show');
            if (firstError) {
                if (firstError.scrollIntoView) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    // Fallback for older browsers
                    firstError.scrollIntoView();
                }
            }
        }
    },
    
    validateField: function(field) {
        var validator = this.validators[field.type];
        if (validator) {
            return validator(field);
        }
        return true;
    },
    
    validateText: function(field) {
        var element = document.getElementById('field_' + field.id);
        var value = element.value.trim();
        
        if (field.required && !value) {
            this.showFieldError(field.id, 'This field is required.');
            return false;
        }
        
        if (field.maxLength && value.length > field.maxLength) {
            this.showFieldError(field.id, 'Maximum length is ' + field.maxLength + ' characters.');
            return false;
        }
        
        this.clearFieldError(field.id);
        return true;
    },
    
    validateEmail: function(field) {
        var element = document.getElementById('field_' + field.id);
        var value = element.value.trim();
        
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
    },
    
    validateNumber: function(field) {
        var element = document.getElementById('field_' + field.id);
        var value = element.value.trim();
        
        if (field.required && !value) {
            this.showFieldError(field.id, 'This field is required.');
            return false;
        }
        
        if (value) {
            var numValue = parseFloat(value);
            
            if (isNaN(numValue)) {
                this.showFieldError(field.id, 'Please enter a valid number.');
                return false;
            }
            
            if (field.min !== null && field.min !== undefined && numValue < field.min) {
                this.showFieldError(field.id, 'Minimum value is ' + field.min + '.');
                return false;
            }
            
            if (field.max !== null && field.max !== undefined && numValue > field.max) {
                this.showFieldError(field.id, 'Maximum value is ' + field.max + '.');
                return false;
            }
        }
        
        this.clearFieldError(field.id);
        return true;
    },
    
    validateAadhar: function(field) {
        var element = document.getElementById('field_' + field.id);
        var value = element.value.replace(/\s/g, ''); // Remove spaces
        
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
    },
    
    validateSelect: function(field) {
        var element = document.getElementById('field_' + field.id);
        var value = element.value;
        
        if (field.required && !value) {
            this.showFieldError(field.id, 'Please select an option.');
            return false;
        }
        
        this.clearFieldError(field.id);
        return true;
    },
    
    validateRadio: function(field) {
        var elements = document.querySelectorAll('input[name="' + field.id + '"]');
        var checked = false;
        
        for (var i = 0; i < elements.length; i++) {
            if (elements[i].checked) {
                checked = true;
                break;
            }
        }
        
        if (field.required && !checked) {
            this.showFieldError(field.id, 'Please select an option.');
            return false;
        }
        
        this.clearFieldError(field.id);
        return true;
    },
    
    validateCheckbox: function(field) {
        var elements = document.querySelectorAll('input[name="' + field.id + '[]"]');
        var checked = false;
        
        for (var i = 0; i < elements.length; i++) {
            if (elements[i].checked) {
                checked = true;
                break;
            }
        }
        
        if (field.required && !checked) {
            this.showFieldError(field.id, 'Please select at least one option.');
            return false;
        }
        
        this.clearFieldError(field.id);
        return true;
    },
    
    validateFile: function(field) {
        var element = document.getElementById('field_' + field.id);
        var file = element.files && element.files[0];
        
        if (field.required && !file) {
            this.showFieldError(field.id, 'Please select a file.');
            return false;
        }
        
        if (file) {
            // Check file size
            var maxSize = (field.maxSize || 5) * 1024 * 1024; // Convert MB to bytes
            if (file.size > maxSize) {
                this.showFieldError(field.id, 'File size must be less than ' + (field.maxSize || 5) + 'MB.');
                return false;
            }
            
            // Check file type
            if (field.allowedTypes && field.allowedTypes.length > 0) {
                var fileName = file.name || '';
                var fileExtension = fileName.split('.').pop().toLowerCase();
                var isAllowed = false;
                
                for (var i = 0; i < field.allowedTypes.length; i++) {
                    if (field.allowedTypes[i] === fileExtension) {
                        isAllowed = true;
                        break;
                    }
                }
                
                if (!isAllowed) {
                    this.showFieldError(field.id, 'Allowed file types: ' + field.allowedTypes.join(', '));
                    return false;
                }
            }
        }
        
        this.clearFieldError(field.id);
        return true;
    },
    
    validatePhoto: function(field) {
        var element = document.getElementById('field_' + field.id);
        var file = element.files && element.files[0];
        
        if (field.required && !file) {
            this.showFieldError(field.id, 'Please select a photo.');
            return false;
        }
        
        if (file) {
            // Check if it's an image
            if (!file.type || file.type.indexOf('image/') !== 0) {
                this.showFieldError(field.id, 'Please select a valid image file.');
                return false;
            }
            
            // Check file size (default 5MB for photos)
            var maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                this.showFieldError(field.id, 'Photo size must be less than 5MB.');
                return false;
            }
        }
        
        this.clearFieldError(field.id);
        return true;
    },
    
    validateSignature: function(field) {
        var element = document.getElementById('field_' + field.id);
        var file = element.files && element.files[0];
        
        if (field.required && !file) {
            this.showFieldError(field.id, 'Please upload your signature.');
            return false;
        }
        
        if (file) {
            // Check if it's an image
            if (!file.type || file.type.indexOf('image/') !== 0) {
                this.showFieldError(field.id, 'Please select a valid image file for signature.');
                return false;
            }
            
            // Check file size (default 2MB for signatures)
            var maxSize = 2 * 1024 * 1024;
            if (file.size > maxSize) {
                this.showFieldError(field.id, 'Signature file size must be less than 2MB.');
                return false;
            }
        }
        
        this.clearFieldError(field.id);
        return true;
    },
    
    showFieldError: function(fieldId, message) {
        var errorElement = document.getElementById('error-' + fieldId);
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.add('show');
        }
        
        var fieldElement = document.getElementById('field_' + fieldId);
        if (fieldElement) {
            fieldElement.classList.add('error');
            fieldElement.setAttribute('aria-invalid', 'true');
            fieldElement.setAttribute('aria-describedby', 'error-' + fieldId);
        }
        
        // Add error styling to form group
        var formGroup = this.getClosest(fieldElement, '.form-group');
        if (formGroup) {
            formGroup.classList.add('has-error');
        }
    },
    
    clearFieldError: function(fieldId) {
        var errorElement = document.getElementById('error-' + fieldId);
        if (errorElement) {
            errorElement.classList.remove('show');
        }
        
        var fieldElement = document.getElementById('field_' + fieldId);
        if (fieldElement) {
            fieldElement.classList.remove('error');
            fieldElement.removeAttribute('aria-invalid');
            fieldElement.removeAttribute('aria-describedby');
        }
        
        // Remove error styling from form group
        var formGroup = this.getClosest(fieldElement, '.form-group');
        if (formGroup) {
            formGroup.classList.remove('has-error');
        }
    },
    
    handleFileUpload: function(event, field) {
        var file = event.target.files && event.target.files[0];
        if (!file) return;
        
        // Show preview for images
        if ((field.type === 'photo' || field.type === 'signature') && 
            file.type && file.type.indexOf('image/') === 0) {
            this.showImagePreview(field.id, file);
        }
        
        // Validate the file
        this.validateField(field);
    },
    
    showImagePreview: function(fieldId, file) {
        var previewContainer = document.getElementById('preview-' + fieldId);
        if (!previewContainer) return;
        
        // Check for FileReader support
        if (!window.FileReader) {
            previewContainer.innerHTML = '<p>Image preview not supported in your browser.</p>';
            return;
        }
        
        var reader = new FileReader();
        var self = this;
        
        reader.onload = function(e) {
            var img = previewContainer.querySelector('.preview-image');
            if (img) {
                img.src = e.target.result;
                previewContainer.style.display = 'flex';
            }
        };
        
        reader.readAsDataURL(file);
    },
    
    formatAadharInput: function(event) {
        var value = event.target.value.replace(/\D/g, ''); // Remove non-digits
        
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
        var fieldId = event.target.id.replace('field_', '');
        var field = null;
        
        for (var i = 0; i < this.fields.length; i++) {
            if (this.fields[i].id === fieldId) {
                field = this.fields[i];
                break;
            }
        }
        
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
    },
    
    isValidEmail: function(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    },
    
    validateAadharRealtime: function(field, element) {
        var value = element.value.replace(/\s/g, '');
        
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
    },
    
    isValidAadhar: function(aadhar) {
        // Remove spaces and check if it's exactly 12 digits
        var cleanAadhar = aadhar.replace(/\s/g, '');
        
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
    },
    
    submitForm: function() {
        var submitBtn = this.form.querySelector('.btn-submit');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            submitBtn.textContent = 'Submitting...';
        }
        
        // Create FormData object if supported, otherwise use regular form submission
        if (window.FormData) {
            var formData = new FormData(this.form);
            
            // Submit the form using fetch or XMLHttpRequest
            this.submitWithAjax(formData, submitBtn);
        } else {
            // Fallback to regular form submission
            this.form.submit();
        }
    },
    
    submitWithAjax: function(formData, submitBtn) {
        var self = this;
        
        // Use fetch if available, otherwise XMLHttpRequest
        if (window.fetch) {
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                return response.text();
            })
            .then(function(html) {
                document.body.innerHTML = html;
            })
            .catch(function(error) {
                self.handleSubmitError(error, submitBtn);
            });
        } else {
            // XMLHttpRequest fallback
            var xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.href);
            
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    document.body.innerHTML = xhr.responseText;
                } else {
                    self.handleSubmitError(new Error('HTTP ' + xhr.status), submitBtn);
                }
            };
            
            xhr.onerror = function() {
                self.handleSubmitError(new Error('Network error'), submitBtn);
            };
            
            xhr.send(formData);
        }
    },
    
    handleSubmitError: function(error, submitBtn) {
        console.error('Error:', error);
        alert('An error occurred while submitting the form. Please try again.');
        
        // Re-enable submit button
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('loading');
            submitBtn.textContent = submitBtn.dataset.originalText || 'Submit';
        }
    },
    
    // Utility function for older browsers that don't support closest()
    getClosest: function(element, selector) {
        if (!element) return null;
        
        // Use native closest if available
        if (element.closest) {
            return element.closest(selector);
        }
        
        // Fallback implementation
        var parent = element.parentNode;
        while (parent && parent !== document) {
            if (this.matchesSelector(parent, selector)) {
                return parent;
            }
            parent = parent.parentNode;
        }
        return null;
    },
    
    // Utility function for selector matching
    matchesSelector: function(element, selector) {
        if (element.matches) {
            return element.matches(selector);
        } else if (element.matchesSelector) {
            return element.matchesSelector(selector);
        } else if (element.webkitMatchesSelector) {
            return element.webkitMatchesSelector(selector);
        } else if (element.mozMatchesSelector) {
            return element.mozMatchesSelector(selector);
        } else if (element.msMatchesSelector) {
            return element.msMatchesSelector(selector);
        } else {
            // Very basic fallback - just check class names
            if (selector.charAt(0) === '.') {
                return element.classList.contains(selector.substring(1));
            }
            return false;
        }
    }
};

// Utility functions for image cropping (will be implemented in task 6.2)
function openCropModal(fieldId) {
    if (window.BrowserSupport && !window.BrowserSupport.features.canvas) {
        alert('Image cropping is not supported in your browser. Please use a modern browser or upload your image as-is.');
        return;
    }
    alert('Image cropping functionality will be implemented in task 6.2');
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // FormValidator will be initialized by the inline script in the HTML
});