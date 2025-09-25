/**
 * Student Enrollment Form Platform - Main JavaScript
 */

// Application namespace
const SEFP = {
    // Configuration
    config: {
        maxFileSize: 5242880, // 5MB
        allowedTypes: ['jpg', 'jpeg', 'png', 'gif'],
        csrfToken: null
    },
    
    // Initialize application
    init: function() {
        this.setupCSRF();
        this.setupFormValidation();
        this.setupFileUploads();
        this.setupImageCropping();
    },
    
    // Setup CSRF token handling
    setupCSRF: function() {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            this.config.csrfToken = csrfMeta.getAttribute('content');
        }
    },
    
    // Setup form validation
    setupFormValidation: function() {
        const forms = document.querySelectorAll('form[data-validate]');
        forms.forEach(form => {
            form.addEventListener('submit', this.validateForm.bind(this));
        });
    },
    
    // Validate form before submission
    validateForm: function(event) {
        const form = event.target;
        let isValid = true;
        
        // Clear previous errors
        this.clearErrors(form);
        
        // Validate required fields
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                this.showFieldError(field, 'This field is required');
                isValid = false;
            }
        });
        
        // Validate email fields
        const emailFields = form.querySelectorAll('input[type="email"]');
        emailFields.forEach(field => {
            if (field.value && !this.isValidEmail(field.value)) {
                this.showFieldError(field, 'Please enter a valid email address');
                isValid = false;
            }
        });
        
        // Validate Aadhar fields
        const aadharFields = form.querySelectorAll('input[data-type="aadhar"]');
        aadharFields.forEach(field => {
            if (field.value && !this.isValidAadhar(field.value)) {
                this.showFieldError(field, 'Please enter a valid 12-digit Aadhar number');
                isValid = false;
            }
        });
        
        if (!isValid) {
            event.preventDefault();
        }
        
        return isValid;
    },
    
    // Setup file upload handling
    setupFileUploads: function() {
        const fileInputs = document.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            input.addEventListener('change', this.handleFileSelect.bind(this));
        });
    },
    
    // Handle file selection
    handleFileSelect: function(event) {
        const input = event.target;
        const files = input.files;
        
        if (files.length === 0) return;
        
        const file = files[0];
        
        // Validate file size
        if (file.size > this.config.maxFileSize) {
            this.showAlert('File size must be less than 5MB', 'error');
            input.value = '';
            return;
        }
        
        // Validate file type
        const fileExtension = file.name.split('.').pop().toLowerCase();
        if (!this.config.allowedTypes.includes(fileExtension)) {
            this.showAlert('Please select a valid image file (JPG, PNG, GIF)', 'error');
            input.value = '';
            return;
        }
        
        // Show file preview if it's an image
        if (file.type.startsWith('image/')) {
            this.showImagePreview(input, file);
        }
    },
    
    // Show image preview
    showImagePreview: function(input, file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            let preview = input.parentNode.querySelector('.image-preview');
            if (!preview) {
                preview = document.createElement('div');
                preview.className = 'image-preview mt-2';
                input.parentNode.appendChild(preview);
            }
            
            preview.innerHTML = `
                <img src="${e.target.result}" alt="Preview" style="max-width: 200px; max-height: 200px; border-radius: 4px;">
                <button type="button" class="btn btn-secondary mt-1" onclick="SEFP.cropImage(this)">Crop Image</button>
            `;
        };
        reader.readAsDataURL(file);
    },
    
    // Setup image cropping (placeholder for future implementation)
    setupImageCropping: function() {
        // This will be implemented in task 6.2
        console.log('Image cropping setup - to be implemented');
    },
    
    // Crop image (placeholder)
    cropImage: function(button) {
        // This will be implemented in task 6.2
        this.showAlert('Image cropping feature will be implemented soon', 'info');
    },
    
    // Utility functions
    isValidEmail: function(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    },
    
    isValidAadhar: function(aadhar) {
        // Remove spaces and hyphens
        aadhar = aadhar.replace(/[\s-]/g, '');
        // Check if it's exactly 12 digits
        return /^\d{12}$/.test(aadhar);
    },
    
    // Show field error
    showFieldError: function(field, message) {
        field.classList.add('error');
        
        let errorDiv = field.parentNode.querySelector('.field-error');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'field-error';
            field.parentNode.appendChild(errorDiv);
        }
        
        errorDiv.textContent = message;
        errorDiv.style.color = '#dc3545';
        errorDiv.style.fontSize = '0.875rem';
        errorDiv.style.marginTop = '0.25rem';
    },
    
    // Clear form errors
    clearErrors: function(form) {
        const errorFields = form.querySelectorAll('.error');
        errorFields.forEach(field => field.classList.remove('error'));
        
        const errorDivs = form.querySelectorAll('.field-error');
        errorDivs.forEach(div => div.remove());
    },
    
    // Show alert message
    showAlert: function(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type}`;
        alertDiv.textContent = message;
        
        // Insert at top of main content
        const mainContent = document.querySelector('.main-content') || document.body;
        mainContent.insertBefore(alertDiv, mainContent.firstChild);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    },
    
    // AJAX helper
    ajax: function(url, options = {}) {
        const defaults = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        // Add CSRF token if available
        if (this.config.csrfToken) {
            defaults.headers['X-CSRF-Token'] = this.config.csrfToken;
        }
        
        const config = Object.assign(defaults, options);
        
        return fetch(url, config)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .catch(error => {
                console.error('AJAX error:', error);
                this.showAlert('An error occurred. Please try again.', 'error');
                throw error;
            });
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    SEFP.init();
});

// Export for global access
window.SEFP = SEFP;