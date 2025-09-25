<?php
/**
 * FormRenderer Class
 * Handles dynamic form rendering from configuration
 */

require_once __DIR__ . '/FormSubmissionHandler.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
require_once __DIR__ . '/../includes/SecurityUtils.php';

class FormRenderer {
    private $form;
    private $config;
    
    public function __construct($form) {
        $this->form = $form;
        $this->config = $form['config'];
    }
    
    /**
     * Render the complete form HTML
     * @return string
     */
    public function renderForm() {
        if (empty($this->config['fields'])) {
            return $this->renderEmptyForm();
        }
        
        $html = '<form id="enrollment-form" class="enrollment-form" method="POST" enctype="multipart/form-data">';
        
        // Add CSRF token
        $csrf = CSRFProtection::getInstance();
        $html .= $csrf->getTokenInput('form_submission');
        
        foreach ($this->config['fields'] as $field) {
            $html .= $this->renderField($field);
        }
        
        $html .= $this->renderFormActions();
        $html .= '</form>';
        
        return $html;
    }
    
    /**
     * Render individual form field
     * @param array $field
     * @return string
     */
    public function renderField($field) {
        $fieldId = 'field_' . $field['id'];
        $fieldName = $field['id'];
        $required = $field['required'] ? 'required' : '';
        $placeholder = htmlspecialchars($field['placeholder'] ?? '');
        
        $html = '<div class="form-group" data-field-type="' . $field['type'] . '">';
        $html .= $this->renderFieldLabel($field);
        $html .= $this->renderFieldInput($field, $fieldId, $fieldName, $required, $placeholder);
        $html .= '<div class="field-error" id="error-' . $field['id'] . '"></div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render field label
     * @param array $field
     * @return string
     */
    private function renderFieldLabel($field) {
        $html = '<label class="field-label" for="field_' . $field['id'] . '">';
        $html .= htmlspecialchars($field['label']);
        if ($field['required']) {
            $html .= '<span class="required">*</span>';
        }
        $html .= '</label>';
        
        return $html;
    }
    
    /**
     * Render field input based on type
     * @param array $field
     * @param string $fieldId
     * @param string $fieldName
     * @param string $required
     * @param string $placeholder
     * @return string
     */
    private function renderFieldInput($field, $fieldId, $fieldName, $required, $placeholder) {
        switch ($field['type']) {
            case 'text':
                return $this->renderTextInput($field, $fieldId, $fieldName, $required, $placeholder);
            case 'email':
                return $this->renderEmailInput($field, $fieldId, $fieldName, $required, $placeholder);
            case 'number':
                return $this->renderNumberInput($field, $fieldId, $fieldName, $required, $placeholder);
            case 'aadhar':
                return $this->renderAadharInput($field, $fieldId, $fieldName, $required, $placeholder);
            case 'select':
                return $this->renderSelectInput($field, $fieldId, $fieldName, $required);
            case 'radio':
                return $this->renderRadioInput($field, $fieldId, $fieldName, $required);
            case 'checkbox':
                return $this->renderCheckboxInput($field, $fieldId, $fieldName);
            case 'file':
                return $this->renderFileInput($field, $fieldId, $fieldName, $required);
            case 'photo':
                return $this->renderPhotoInput($field, $fieldId, $fieldName, $required);
            case 'signature':
                return $this->renderSignatureInput($field, $fieldId, $fieldName, $required);
            default:
                return $this->renderTextInput($field, $fieldId, $fieldName, $required, $placeholder);
        }
    }
    
    /**
     * Render text input
     */
    private function renderTextInput($field, $fieldId, $fieldName, $required, $placeholder) {
        $maxLength = isset($field['maxLength']) ? "maxlength=\"{$field['maxLength']}\"" : '';
        return "<input type=\"text\" id=\"{$fieldId}\" name=\"{$fieldName}\" placeholder=\"{$placeholder}\" {$required} {$maxLength} class=\"form-control\">";
    }
    
    /**
     * Render email input
     */
    private function renderEmailInput($field, $fieldId, $fieldName, $required, $placeholder) {
        return "<input type=\"email\" id=\"{$fieldId}\" name=\"{$fieldName}\" placeholder=\"{$placeholder}\" {$required} class=\"form-control\">";
    }
    
    /**
     * Render number input
     */
    private function renderNumberInput($field, $fieldId, $fieldName, $required, $placeholder) {
        $min = isset($field['min']) ? "min=\"{$field['min']}\"" : '';
        $max = isset($field['max']) ? "max=\"{$field['max']}\"" : '';
        return "<input type=\"number\" id=\"{$fieldId}\" name=\"{$fieldName}\" placeholder=\"{$placeholder}\" {$required} {$min} {$max} class=\"form-control\">";
    }
    
    /**
     * Render Aadhar input with special formatting
     */
    private function renderAadharInput($field, $fieldId, $fieldName, $required, $placeholder) {
        $placeholder = $placeholder ?: 'XXXX XXXX XXXX';
        $html = "<input type='text' id='{$fieldId}' name='{$fieldName}' placeholder='{$placeholder}' {$required} maxlength='14' class='form-control aadhar-input' data-type='aadhar' autocomplete='off' inputmode='numeric'>";
        $html .= "<small class='field-help'>Enter your 12-digit Aadhar number. It will be automatically formatted as you type.</small>";
        return $html;
    }
    
    /**
     * Render select dropdown
     */
    private function renderSelectInput($field, $fieldId, $fieldName, $required) {
        $html = "<select id=\"{$fieldId}\" name=\"{$fieldName}\" {$required} class=\"form-control\">";
        $html .= "<option value=\"\">Select an option...</option>";
        
        if (!empty($field['options'])) {
            foreach ($field['options'] as $option) {
                $optionValue = htmlspecialchars($option);
                $html .= "<option value=\"{$optionValue}\">{$optionValue}</option>";
            }
        }
        
        $html .= "</select>";
        return $html;
    }
    
    /**
     * Render radio buttons
     */
    private function renderRadioInput($field, $fieldId, $fieldName, $required) {
        $html = '<div class="radio-group">';
        
        if (!empty($field['options'])) {
            foreach ($field['options'] as $index => $option) {
                $optionValue = htmlspecialchars($option);
                $optionId = $fieldId . '_' . $index;
                $html .= '<label class="radio-option">';
                $html .= "<input type=\"radio\" id=\"{$optionId}\" name=\"{$fieldName}\" value=\"{$optionValue}\" {$required}>";
                $html .= "<span class=\"radio-label\">{$optionValue}</span>";
                $html .= '</label>';
            }
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render checkboxes
     */
    private function renderCheckboxInput($field, $fieldId, $fieldName) {
        $html = '<div class="checkbox-group">';
        
        if (!empty($field['options'])) {
            foreach ($field['options'] as $index => $option) {
                $optionValue = htmlspecialchars($option);
                $optionId = $fieldId . '_' . $index;
                $html .= '<label class="checkbox-option">';
                $html .= "<input type=\"checkbox\" id=\"{$optionId}\" name=\"{$fieldName}[]\" value=\"{$optionValue}\">";
                $html .= "<span class=\"checkbox-label\">{$optionValue}</span>";
                $html .= '</label>';
            }
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Render file input
     */
    private function renderFileInput($field, $fieldId, $fieldName, $required) {
        $accept = '';
        if (!empty($field['allowedTypes'])) {
            $accept = 'accept=".' . implode(',.', $field['allowedTypes']) . '"';
        }
        $maxSize = $field['maxSize'] ?? 5;
        
        $html = "<input type=\"file\" id=\"{$fieldId}\" name=\"{$fieldName}\" {$required} {$accept} class=\"form-control file-input\" data-max-size=\"{$maxSize}\" data-preupload=\"1\" data-field-name=\"{$fieldName}\">";
        // Hidden input to carry temp token from pre-upload flow
        $html .= "<input type=\"hidden\" id=\"{$fieldId}_temp\" name=\"{$fieldName}_temp\" value=\"\">";
        return $html;
    }
    
    /**
     * Render photo input with preview
     */
    private function renderPhotoInput($field, $fieldId, $fieldName, $required) {
        $allowCropping = $field['allowCropping'] ?? true;
        $aspectRatio = $field['aspectRatio'] ?? '3:4';
        
        $html = '<div class="photo-upload-container">';
        $html .= "<input type=\"file\" id=\"{$fieldId}\" name=\"{$fieldName}\" {$required} accept=\"image/*\" class=\"form-control photo-input\" data-allow-cropping=\"{$allowCropping}\" data-aspect-ratio=\"{$aspectRatio}\" data-preupload=\"1\" data-field-name=\"{$fieldName}\">";
        $html .= "<input type=\"hidden\" id=\"{$fieldId}_temp\" name=\"{$fieldName}_temp\" value=\"\">";
        $html .= "<div class=\"photo-preview\" id=\"preview-{$fieldId}\" style=\"display: none;\">";
        $html .= '<img class="preview-image" alt="Photo preview">';
        if ($allowCropping) {
            $html .= "<button type=\"button\" class=\"btn btn-secondary btn-sm crop-btn\" onclick=\"openCropModal('{$fieldId}')\">Crop Image</button>";
        }
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render signature input with preview
     */
    private function renderSignatureInput($field, $fieldId, $fieldName, $required) {
        $allowCropping = $field['allowCropping'] ?? true;
        
        $html = '<div class="signature-upload-container">';
        $html .= "<input type=\"file\" id=\"{$fieldId}\" name=\"{$fieldName}\" {$required} accept=\"image/*\" class=\"form-control signature-input\" data-allow-cropping=\"{$allowCropping}\" data-preupload=\"1\" data-field-name=\"{$fieldName}\">";
        $html .= "<input type=\"hidden\" id=\"{$fieldId}_temp\" name=\"{$fieldName}_temp\" value=\"\">";
        $html .= "<div class=\"signature-preview\" id=\"preview-{$fieldId}\" style=\"display: none;\">";
        $html .= '<img class="preview-image" alt="Signature preview">';
        if ($allowCropping) {
            $html .= "<button type=\"button\" class=\"btn btn-secondary btn-sm crop-btn\" onclick=\"openCropModal('{$fieldId}')\">Crop Signature</button>";
        }
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render form actions (submit button)
     */
    private function renderFormActions() {
        $submitText = $this->config['settings']['submit_button_text'] ?? 'Submit';
        
        $html = '<div class="form-actions">';
        $html .= '<button type="submit" class="btn btn-primary btn-submit">';
        $html .= htmlspecialchars($submitText);
        $html .= '</button>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render empty form message
     */
    private function renderEmptyForm() {
        return '<div class="empty-form"><p>This form has no fields configured yet.</p></div>';
    }
    
    /**
     * Get JavaScript configuration for form validation
     * @return string JSON configuration
     */
    public function getValidationConfig() {
        return json_encode($this->config['fields'] ?? []);
    }
    
    /**
     * Validate form configuration
     * @return array Validation errors
     */
    public function validateConfig() {
        $errors = [];
        
        if (empty($this->config['fields'])) {
            $errors[] = 'Form must have at least one field';
        }
        
        foreach ($this->config['fields'] as $index => $field) {
            $fieldErrors = $this->validateFieldConfig($field, $index);
            $errors = array_merge($errors, $fieldErrors);
        }
        
        return $errors;
    }
    
    /**
     * Validate individual field configuration
     * @param array $field
     * @param int $index
     * @return array
     */
    private function validateFieldConfig($field, $index) {
        $errors = [];
        
        // Required properties
        if (empty($field['id'])) {
            $errors[] = "Field {$index}: Missing field ID";
        }
        
        if (empty($field['type'])) {
            $errors[] = "Field {$index}: Missing field type";
        }
        
        if (empty($field['label'])) {
            $errors[] = "Field {$index}: Missing field label";
        }
        
        // Type-specific validation
        if (!empty($field['type'])) {
            switch ($field['type']) {
                case 'select':
                case 'radio':
                case 'checkbox':
                    if (empty($field['options']) || !is_array($field['options'])) {
                        $errors[] = "Field {$index}: {$field['type']} field must have options";
                    }
                    break;
                    
                case 'number':
                    if (isset($field['min']) && isset($field['max']) && $field['min'] > $field['max']) {
                        $errors[] = "Field {$index}: Minimum value cannot be greater than maximum value";
                    }
                    break;
                    
                case 'file':
                    if (isset($field['maxSize']) && $field['maxSize'] <= 0) {
                        $errors[] = "Field {$index}: Maximum file size must be greater than 0";
                    }
                    break;
            }
        }
        
        return $errors;
    }
}
?>