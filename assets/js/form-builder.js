/**
 * Form Builder JavaScript
 * Handles drag-and-drop functionality, field configuration, and form preview
 */

class FormBuilder {
    constructor(options = {}) {
        this.formId = options.formId || null;
        this.fields = [];
        this.selectedField = null;
        this.draggedFieldType = null;
        
        this.initializeElements();
        this.attachEventListeners();
        
        // Load initial data if provided
        if (options.initialData) {
            this.loadFormData(options.initialData);
        }
    }
    
    initializeElements() {
        this.dropZone = document.getElementById('drop-zone');
        this.formBuilder = document.getElementById('form-builder');
        this.fieldConfigPanel = document.getElementById('field-config-panel');
        this.fieldConfigContent = document.getElementById('field-config-content');
        this.previewModal = document.getElementById('preview-modal');
        this.previewContent = document.getElementById('preview-content');
        
        // Form settings elements
        this.formTitle = document.getElementById('form-title');
        this.formDescription = document.getElementById('form-description');
        this.submitButtonText = document.getElementById('submit-button-text');
        this.successMessage = document.getElementById('success-message');
        
        // Button elements
        this.saveBtn = document.getElementById('save-btn');
        this.previewBtn = document.getElementById('preview-btn');
        this.clearFormBtn = document.getElementById('clear-form');
        this.applyConfigBtn = document.getElementById('apply-config');
        this.cancelConfigBtn = document.getElementById('cancel-config');
        this.deleteFieldBtn = document.getElementById('delete-field');
    }
    
    attachEventListeners() {
        // Drag and drop for field types
        const fieldTypes = document.querySelectorAll('.field-type');
        fieldTypes.forEach(fieldType => {
            fieldType.addEventListener('dragstart', this.handleDragStart.bind(this));
            fieldType.addEventListener('dragend', this.handleDragEnd.bind(this));
        });
        
        // Drop zone events
        this.dropZone.addEventListener('dragover', this.handleDragOver.bind(this));
        this.dropZone.addEventListener('drop', this.handleDrop.bind(this));
        this.dropZone.addEventListener('dragleave', this.handleDragLeave.bind(this));
        
        // Button events
        this.saveBtn.addEventListener('click', this.saveForm.bind(this));
        this.previewBtn.addEventListener('click', this.previewForm.bind(this));
        this.clearFormBtn.addEventListener('click', this.clearForm.bind(this));
        this.applyConfigBtn.addEventListener('click', this.applyFieldConfig.bind(this));
        this.cancelConfigBtn.addEventListener('click', this.cancelFieldConfig.bind(this));
        this.deleteFieldBtn.addEventListener('click', this.deleteField.bind(this));
        
        // Modal events
        const modalClose = document.querySelector('.modal-close');
        if (modalClose) {
            modalClose.addEventListener('click', this.closePreview.bind(this));
        }
        
        // Click outside modal to close
        this.previewModal.addEventListener('click', (e) => {
            if (e.target === this.previewModal) {
                this.closePreview();
            }
        });
    }
    
    handleDragStart(e) {
        this.draggedFieldType = e.target.dataset.type;
        e.target.style.opacity = '0.5';
    }
    
    handleDragEnd(e) {
        e.target.style.opacity = '1';
        this.draggedFieldType = null;
    }
    
    handleDragOver(e) {
        e.preventDefault();
        this.dropZone.classList.add('drag-over');
    }
    
    handleDragLeave(e) {
        if (!this.dropZone.contains(e.relatedTarget)) {
            this.dropZone.classList.remove('drag-over');
        }
    }
    
    handleDrop(e) {
        e.preventDefault();
        this.dropZone.classList.remove('drag-over');
        
        if (this.draggedFieldType) {
            this.addField(this.draggedFieldType);
        }
    }
    
    addField(fieldType) {
        const fieldId = this.generateId();
        const fieldConfig = this.getDefaultFieldConfig(fieldType);
        fieldConfig.id = fieldId;
        
        this.fields.push(fieldConfig);
        this.renderFields();
        this.selectField(fieldId);
    }
    
    getDefaultFieldConfig(fieldType) {
        const configs = {
            text: {
                type: 'text',
                label: 'Text Input',
                placeholder: 'Enter text...',
                required: false,
                maxLength: null
            },
            email: {
                type: 'email',
                label: 'Email Address',
                placeholder: 'Enter email address...',
                required: false
            },
            number: {
                type: 'number',
                label: 'Number',
                placeholder: 'Enter number...',
                required: false,
                min: null,
                max: null
            },
            aadhar: {
                type: 'aadhar',
                label: 'Aadhar Number',
                placeholder: 'Enter 12-digit Aadhar number...',
                required: false
            },
            select: {
                type: 'select',
                label: 'Dropdown',
                required: false,
                options: ['Option 1', 'Option 2', 'Option 3']
            },
            radio: {
                type: 'radio',
                label: 'Radio Buttons',
                required: false,
                options: ['Option 1', 'Option 2', 'Option 3']
            },
            checkbox: {
                type: 'checkbox',
                label: 'Checkboxes',
                required: false,
                options: ['Option 1', 'Option 2', 'Option 3']
            },
            file: {
                type: 'file',
                label: 'File Upload',
                required: false,
                allowedTypes: ['pdf', 'doc', 'docx', 'jpg', 'png'],
                maxSize: 5 // MB
            },
            photo: {
                type: 'photo',
                label: 'Photo Upload',
                required: false,
                allowCropping: true,
                aspectRatio: '1:1'
            },
            signature: {
                type: 'signature',
                label: 'Signature',
                required: false,
                allowCropping: true
            }
        };
        
        return configs[fieldType] || configs.text;
    }
    
    renderFields() {
        if (this.fields.length === 0) {
            this.dropZone.innerHTML = '<p class="drop-message">Drag field types here to build your form</p>';
            return;
        }
        
        const fieldsHtml = this.fields.map(field => this.renderField(field)).join('');
        this.dropZone.innerHTML = fieldsHtml;
        
        // Attach click events to fields
        const fieldElements = this.dropZone.querySelectorAll('.form-field');
        fieldElements.forEach(fieldEl => {
            fieldEl.addEventListener('click', (e) => {
                e.stopPropagation();
                this.selectField(fieldEl.dataset.fieldId);
            });
        });
    }
    
    renderField(field) {
        const requiredMark = field.required ? '<span class="field-required">*</span>' : '';
        
        return `
            <div class="form-field" data-field-id="${field.id}">
                <div class="field-header">
                    <div class="field-label">${field.label}${requiredMark}</div>
                    <div class="field-actions">
                        <button class="field-action" onclick="formBuilder.moveFieldUp('${field.id}')" title="Move Up">↑</button>
                        <button class="field-action" onclick="formBuilder.moveFieldDown('${field.id}')" title="Move Down">↓</button>
                        <button class="field-action" onclick="formBuilder.selectField('${field.id}')" title="Configure">⚙</button>
                        <button class="field-action" onclick="formBuilder.removeField('${field.id}')" title="Delete">×</button>
                    </div>
                </div>
                <div class="field-preview">
                    ${this.renderFieldPreview(field)}
                </div>
            </div>
        `;
    }
    
    renderFieldPreview(field) {
        switch (field.type) {
            case 'text':
            case 'email':
            case 'number':
            case 'aadhar':
                return `<input type="${field.type === 'aadhar' ? 'text' : field.type}" placeholder="${field.placeholder || ''}" disabled>`;
            
            case 'select':
                const selectOptions = field.options.map(opt => `<option>${opt}</option>`).join('');
                return `<select disabled><option>Select an option...</option>${selectOptions}</select>`;
            
            case 'radio':
                const radioOptions = field.options.map((opt, idx) => 
                    `<label><input type="radio" name="preview_${field.id}" disabled> ${opt}</label>`
                ).join('<br>');
                return radioOptions;
            
            case 'checkbox':
                const checkboxOptions = field.options.map((opt, idx) => 
                    `<label><input type="checkbox" disabled> ${opt}</label>`
                ).join('<br>');
                return checkboxOptions;
            
            case 'file':
            case 'photo':
            case 'signature':
                return `<input type="file" disabled> <small>Max size: ${field.maxSize || 5}MB</small>`;
            
            default:
                return `<input type="text" placeholder="${field.placeholder || ''}" disabled>`;
        }
    }
    
    selectField(fieldId) {
        // Remove previous selection
        const prevSelected = this.dropZone.querySelector('.form-field.selected');
        if (prevSelected) {
            prevSelected.classList.remove('selected');
        }
        
        // Select new field
        const fieldEl = this.dropZone.querySelector(`[data-field-id="${fieldId}"]`);
        if (fieldEl) {
            fieldEl.classList.add('selected');
            this.selectedField = this.fields.find(f => f.id === fieldId);
            this.showFieldConfig(this.selectedField);
        }
    }
    
    showFieldConfig(field) {
        this.fieldConfigContent.innerHTML = this.generateFieldConfigHTML(field);
        this.fieldConfigPanel.style.display = 'block';
        
        // Attach events for dynamic options
        this.attachConfigEvents();
    }
    
    generateFieldConfigHTML(field) {
        let html = `
            <div class="config-section">
                <h4>Basic Settings</h4>
                <div class="form-group">
                    <label>Field Label</label>
                    <input type="text" id="config-label" value="${field.label}">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="config-required" ${field.required ? 'checked' : ''}>
                        Required Field
                    </label>
                </div>
        `;
        
        // Type-specific configurations
        switch (field.type) {
            case 'text':
            case 'email':
            case 'aadhar':
                html += `
                    <div class="form-group">
                        <label>Placeholder Text</label>
                        <input type="text" id="config-placeholder" value="${field.placeholder || ''}">
                    </div>
                `;
                if (field.type === 'text') {
                    html += `
                        <div class="form-group">
                            <label>Maximum Length</label>
                            <input type="number" id="config-maxlength" value="${field.maxLength || ''}" placeholder="No limit">
                        </div>
                    `;
                }
                break;
                
            case 'number':
                html += `
                    <div class="form-group">
                        <label>Placeholder Text</label>
                        <input type="text" id="config-placeholder" value="${field.placeholder || ''}">
                    </div>
                    <div class="form-group">
                        <label>Minimum Value</label>
                        <input type="number" id="config-min" value="${field.min || ''}" placeholder="No minimum">
                    </div>
                    <div class="form-group">
                        <label>Maximum Value</label>
                        <input type="number" id="config-max" value="${field.max || ''}" placeholder="No maximum">
                    </div>
                `;
                break;
                
            case 'select':
            case 'radio':
            case 'checkbox':
                html += `
                    <div class="form-group">
                        <label>Options</label>
                        <div class="option-list" id="option-list">
                `;
                field.options.forEach((option, index) => {
                    html += `
                        <div class="option-item">
                            <input type="text" value="${option}" data-index="${index}">
                            <button type="button" onclick="formBuilder.removeOption(${index})">×</button>
                        </div>
                    `;
                });
                html += `
                        </div>
                        <button type="button" class="add-option" onclick="formBuilder.addOption()">Add Option</button>
                    </div>
                `;
                break;
                
            case 'file':
                html += `
                    <div class="form-group">
                        <label>Allowed File Types (comma separated)</label>
                        <input type="text" id="config-filetypes" value="${field.allowedTypes.join(', ')}">
                    </div>
                    <div class="form-group">
                        <label>Maximum File Size (MB)</label>
                        <input type="number" id="config-maxsize" value="${field.maxSize}" min="1" max="50">
                    </div>
                `;
                break;
                
            case 'photo':
                html += `
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="config-cropping" ${field.allowCropping ? 'checked' : ''}>
                            Allow Image Cropping
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Aspect Ratio</label>
                        <select id="config-aspectratio">
                            <option value="1:1" ${field.aspectRatio === '1:1' ? 'selected' : ''}>Square (1:1)</option>
                            <option value="4:3" ${field.aspectRatio === '4:3' ? 'selected' : ''}>Standard (4:3)</option>
                            <option value="16:9" ${field.aspectRatio === '16:9' ? 'selected' : ''}>Widescreen (16:9)</option>
                            <option value="free" ${field.aspectRatio === 'free' ? 'selected' : ''}>Free Form</option>
                        </select>
                    </div>
                `;
                break;
                
            case 'signature':
                html += `
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="config-cropping" ${field.allowCropping ? 'checked' : ''}>
                            Allow Signature Cropping
                        </label>
                    </div>
                `;
                break;
        }
        
        html += '</div>';
        return html;
    }
    
    attachConfigEvents() {
        // Add option functionality for select/radio/checkbox fields
        if (this.selectedField && ['select', 'radio', 'checkbox'].includes(this.selectedField.type)) {
            const optionInputs = this.fieldConfigContent.querySelectorAll('.option-item input');
            optionInputs.forEach(input => {
                input.addEventListener('input', (e) => {
                    const index = parseInt(e.target.dataset.index);
                    this.selectedField.options[index] = e.target.value;
                });
            });
        }
    }
    
    addOption() {
        if (this.selectedField && ['select', 'radio', 'checkbox'].includes(this.selectedField.type)) {
            this.selectedField.options.push('New Option');
            this.showFieldConfig(this.selectedField);
        }
    }
    
    removeOption(index) {
        if (this.selectedField && ['select', 'radio', 'checkbox'].includes(this.selectedField.type)) {
            this.selectedField.options.splice(index, 1);
            this.showFieldConfig(this.selectedField);
        }
    }
    
    applyFieldConfig() {
        if (!this.selectedField) return;
        
        // Get basic settings
        const label = document.getElementById('config-label')?.value || this.selectedField.label;
        const required = document.getElementById('config-required')?.checked || false;
        
        this.selectedField.label = label;
        this.selectedField.required = required;
        
        // Get type-specific settings
        const placeholder = document.getElementById('config-placeholder')?.value;
        if (placeholder !== undefined) {
            this.selectedField.placeholder = placeholder;
        }
        
        const maxLength = document.getElementById('config-maxlength')?.value;
        if (maxLength !== undefined) {
            this.selectedField.maxLength = maxLength ? parseInt(maxLength) : null;
        }
        
        const min = document.getElementById('config-min')?.value;
        if (min !== undefined) {
            this.selectedField.min = min ? parseFloat(min) : null;
        }
        
        const max = document.getElementById('config-max')?.value;
        if (max !== undefined) {
            this.selectedField.max = max ? parseFloat(max) : null;
        }
        
        const fileTypes = document.getElementById('config-filetypes')?.value;
        if (fileTypes !== undefined) {
            this.selectedField.allowedTypes = fileTypes.split(',').map(t => t.trim()).filter(t => t);
        }
        
        const maxSize = document.getElementById('config-maxsize')?.value;
        if (maxSize !== undefined) {
            this.selectedField.maxSize = maxSize ? parseInt(maxSize) : 5;
        }
        
        const allowCropping = document.getElementById('config-cropping')?.checked;
        if (allowCropping !== undefined) {
            this.selectedField.allowCropping = allowCropping;
        }
        
        const aspectRatio = document.getElementById('config-aspectratio')?.value;
        if (aspectRatio !== undefined) {
            this.selectedField.aspectRatio = aspectRatio;
        }
        
        this.renderFields();
        this.cancelFieldConfig();
    }
    
    cancelFieldConfig() {
        this.fieldConfigPanel.style.display = 'none';
        this.selectedField = null;
        
        // Remove selection
        const selected = this.dropZone.querySelector('.form-field.selected');
        if (selected) {
            selected.classList.remove('selected');
        }
    }
    
    deleteField() {
        if (this.selectedField) {
            this.removeField(this.selectedField.id);
            this.cancelFieldConfig();
        }
    }
    
    removeField(fieldId) {
        this.fields = this.fields.filter(f => f.id !== fieldId);
        this.renderFields();
        
        if (this.selectedField && this.selectedField.id === fieldId) {
            this.cancelFieldConfig();
        }
    }
    
    moveFieldUp(fieldId) {
        const index = this.fields.findIndex(f => f.id === fieldId);
        if (index > 0) {
            [this.fields[index - 1], this.fields[index]] = [this.fields[index], this.fields[index - 1]];
            this.renderFields();
        }
    }
    
    moveFieldDown(fieldId) {
        const index = this.fields.findIndex(f => f.id === fieldId);
        if (index < this.fields.length - 1) {
            [this.fields[index], this.fields[index + 1]] = [this.fields[index + 1], this.fields[index]];
            this.renderFields();
        }
    }
    
    clearForm() {
        if (confirm('Are you sure you want to clear all fields? This action cannot be undone.')) {
            this.fields = [];
            this.renderFields();
            this.cancelFieldConfig();
        }
    }
    
    saveForm() {
        const title = this.formTitle.value.trim();
        if (!title) {
            alert('Please enter a form title.');
            return;
        }
        
        const formData = {
            fields: this.fields,
            settings: {
                submit_button_text: this.submitButtonText.value || 'Submit',
                success_message: this.successMessage.value || 'Thank you for your submission!',
                allow_multiple_submissions: false
            }
        };
        
        const formDataToSend = new FormData();
        formDataToSend.append('action', 'save_form');
        formDataToSend.append('title', title);
        formDataToSend.append('description', this.formDescription.value);
        formDataToSend.append('config', JSON.stringify(formData));
        
        fetch(window.location.href, {
            method: 'POST',
            body: formDataToSend
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Form saved successfully!');
                if (data.form_id && !this.formId) {
                    // Redirect to edit mode for new forms
                    window.location.href = `form-builder.php?id=${data.form_id}`;
                }
            } else {
                alert('Error saving form. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error saving form. Please try again.');
        });
    }
    
    previewForm() {
        const formData = {
            fields: this.fields,
            settings: {
                submit_button_text: this.submitButtonText.value || 'Submit',
                success_message: this.successMessage.value || 'Thank you for your submission!'
            }
        };
        
        // Generate preview HTML
        let previewHtml = `
            <form class="form-preview">
                <h2>${this.formTitle.value || 'Untitled Form'}</h2>
        `;
        
        if (this.formDescription.value) {
            previewHtml += `<p class="form-description">${this.formDescription.value}</p>`;
        }
        
        this.fields.forEach(field => {
            previewHtml += this.generateFieldHTML(field);
        });
        
        previewHtml += `
                <button type="submit" class="btn btn-primary">${formData.settings.submit_button_text}</button>
            </form>
        `;
        
        this.previewContent.innerHTML = previewHtml;
        this.previewModal.style.display = 'flex';
    }
    
    generateFieldHTML(field) {
        const requiredAttr = field.required ? 'required' : '';
        const requiredMark = field.required ? '<span class="required">*</span>' : '';
        
        let html = `<div class="form-group">
            <label>${field.label}${requiredMark}</label>`;
        
        switch (field.type) {
            case 'text':
            case 'email':
            case 'number':
                html += `<input type="${field.type}" placeholder="${field.placeholder || ''}" ${requiredAttr}>`;
                break;
                
            case 'aadhar':
                html += `<input type="text" placeholder="${field.placeholder || ''}" pattern="[0-9]{12}" maxlength="12" ${requiredAttr}>`;
                break;
                
            case 'select':
                html += `<select ${requiredAttr}>
                    <option value="">Select an option...</option>`;
                field.options.forEach(option => {
                    html += `<option value="${option}">${option}</option>`;
                });
                html += '</select>';
                break;
                
            case 'radio':
                field.options.forEach((option, index) => {
                    html += `<label class="radio-option">
                        <input type="radio" name="${field.id}" value="${option}" ${requiredAttr}>
                        ${option}
                    </label>`;
                });
                break;
                
            case 'checkbox':
                field.options.forEach((option, index) => {
                    html += `<label class="checkbox-option">
                        <input type="checkbox" name="${field.id}[]" value="${option}">
                        ${option}
                    </label>`;
                });
                break;
                
            case 'file':
            case 'photo':
            case 'signature':
                const accept = field.type === 'photo' ? 'image/*' : 
                              field.type === 'signature' ? 'image/*' : 
                              field.allowedTypes ? field.allowedTypes.map(t => `.${t}`).join(',') : '';
                html += `<input type="file" accept="${accept}" ${requiredAttr}>`;
                break;
        }
        
        html += '</div>';
        return html;
    }
    
    closePreview() {
        this.previewModal.style.display = 'none';
    }
    
    loadFormData(data) {
        if (data.fields) {
            this.fields = data.fields;
            this.renderFields();
        }
        
        if (data.settings) {
            this.submitButtonText.value = data.settings.submit_button_text || 'Submit';
            this.successMessage.value = data.settings.success_message || 'Thank you for your submission!';
        }
    }
    
    generateId() {
        return 'field_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
}

// Initialize form builder when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // FormBuilder will be initialized by the inline script in the HTML
});