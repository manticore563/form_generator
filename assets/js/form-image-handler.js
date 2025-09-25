class FormImageHandler {
    constructor() {
        this.croppers = new Map();
        this.uploadedFiles = new Map();
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.setupImageFields();
    }
    
    bindEvents() {
        // Handle form submission
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('form-with-images')) {
                this.handleFormSubmit(e);
            }
        });
    }
    
    setupImageFields() {
        const imageFields = document.querySelectorAll('.image-field');
        imageFields.forEach(field => this.setupImageField(field));
    }
    
    setupImageField(fieldElement) {
        const fieldName = fieldElement.dataset.fieldName;
        const fieldType = fieldElement.dataset.fieldType || 'photo';
        const required = fieldElement.dataset.required === 'true';
        
        // Create cropper container
        const cropperContainer = document.createElement('div');
        cropperContainer.id = `cropper-${fieldName}`;
        cropperContainer.className = 'image-cropper-container';
        
        fieldElement.appendChild(cropperContainer);
        
        // Create cropper instance
        const cropper = createImageCropper(cropperContainer.id, fieldType);
        this.croppers.set(fieldName, cropper);
        
        // Add upload button event
        const uploadBtn = cropperContainer.querySelector('#cropBtn');
        if (uploadBtn) {
            uploadBtn.addEventListener('click', () => {
                this.handleImageCrop(fieldName, cropper);
            });
        }
        
        // Add validation
        if (required) {
            this.addFieldValidation(fieldElement, fieldName);
        }
    }
    
    async handleImageCrop(fieldName, cropper) {
        try {
            // Get cropped image blob
            const croppedBlob = await cropper.cropImage();
            if (!croppedBlob) {
                throw new Error('Failed to crop image');
            }
            
            // Upload cropped image
            const uploadResult = await this.uploadImage(croppedBlob, fieldName);
            if (!uploadResult.success) {
                throw new Error(uploadResult.error);
            }
            
            // Store file information
            this.uploadedFiles.set(fieldName, {
                fileId: uploadResult.fileId,
                filename: uploadResult.filename,
                blob: croppedBlob
            });
            
            // Update UI
            this.updateFieldStatus(fieldName, 'success', 'Image uploaded successfully');
            
            // Enable form submission
            this.validateForm();
            
        } catch (error) {
            console.error('Image crop error:', error);
            this.updateFieldStatus(fieldName, 'error', error.message);
        }
    }
    
    async uploadImage(blob, fieldName) {
        const formData = new FormData();
        formData.append('file', blob, `${fieldName}_${Date.now()}.jpg`);
        formData.append('field_name', fieldName);
        formData.append('submission_id', this.getSubmissionId());
        formData.append('action', 'upload');
        
        try {
            const response = await fetch('/uploads/index.php', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
            
        } catch (error) {
            console.error('Upload error:', error);
            return { success: false, error: error.message };
        }
    }
    
    getSubmissionId() {
        // Generate or retrieve submission ID
        let submissionId = sessionStorage.getItem('current_submission_id');
        if (!submissionId) {
            submissionId = this.generateSubmissionId();
            sessionStorage.setItem('current_submission_id', submissionId);
        }
        return submissionId;
    }
    
    generateSubmissionId() {
        return 'sub_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    updateFieldStatus(fieldName, status, message) {
        const fieldElement = document.querySelector(`[data-field-name="${fieldName}"]`);
        if (!fieldElement) return;
        
        // Remove existing status classes
        fieldElement.classList.remove('field-success', 'field-error', 'field-loading');
        
        // Add new status class
        fieldElement.classList.add(`field-${status}`);
        
        // Update status message
        let statusElement = fieldElement.querySelector('.field-status');
        if (!statusElement) {
            statusElement = document.createElement('div');
            statusElement.className = 'field-status';
            fieldElement.appendChild(statusElement);
        }
        
        statusElement.textContent = message;
        statusElement.className = `field-status status-${status}`;
    }
    
    addFieldValidation(fieldElement, fieldName) {
        const form = fieldElement.closest('form');
        if (!form) return;
        
        form.addEventListener('submit', (e) => {
            if (!this.uploadedFiles.has(fieldName)) {
                e.preventDefault();
                this.updateFieldStatus(fieldName, 'error', 'Please upload and crop an image');
                fieldElement.scrollIntoView({ behavior: 'smooth' });
                return false;
            }
        });
    }
    
    validateForm() {
        const form = document.querySelector('.form-with-images');
        if (!form) return;
        
        const requiredImageFields = form.querySelectorAll('.image-field[data-required="true"]');
        let allValid = true;
        
        requiredImageFields.forEach(field => {
            const fieldName = field.dataset.fieldName;
            if (!this.uploadedFiles.has(fieldName)) {
                allValid = false;
            }
        });
        
        // Enable/disable submit button
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = !allValid;
        }
        
        return allValid;
    }
    
    handleFormSubmit(e) {
        e.preventDefault();
        
        if (!this.validateForm()) {
            return false;
        }
        
        // Add uploaded file IDs to form data
        const form = e.target;
        this.uploadedFiles.forEach((fileData, fieldName) => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = `uploaded_files[${fieldName}]`;
            hiddenInput.value = fileData.fileId;
            form.appendChild(hiddenInput);
        });
        
        // Submit form normally
        form.submit();
    }
    
    // Utility methods for external use
    getUploadedFile(fieldName) {
        return this.uploadedFiles.get(fieldName);
    }
    
    removeUploadedFile(fieldName) {
        const fileData = this.uploadedFiles.get(fieldName);
        if (fileData) {
            // Optionally call API to delete file
            this.uploadedFiles.delete(fieldName);
            this.updateFieldStatus(fieldName, '', '');
            this.validateForm();
        }
    }
    
    resetField(fieldName) {
        const cropper = this.croppers.get(fieldName);
        if (cropper) {
            cropper.reset();
        }
        this.removeUploadedFile(fieldName);
    }
    
    resetAllFields() {
        this.croppers.forEach((cropper, fieldName) => {
            this.resetField(fieldName);
        });
        sessionStorage.removeItem('current_submission_id');
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.formImageHandler = new FormImageHandler();
});

// Utility functions for form integration
function addImageField(container, fieldName, fieldType = 'photo', required = false) {
    const fieldElement = document.createElement('div');
    fieldElement.className = 'image-field';
    fieldElement.dataset.fieldName = fieldName;
    fieldElement.dataset.fieldType = fieldType;
    fieldElement.dataset.required = required.toString();
    
    container.appendChild(fieldElement);
    
    // Setup the field if handler is ready
    if (window.formImageHandler) {
        window.formImageHandler.setupImageField(fieldElement);
    }
    
    return fieldElement;
}

function getImageFieldValue(fieldName) {
    if (window.formImageHandler) {
        return window.formImageHandler.getUploadedFile(fieldName);
    }
    return null;
}