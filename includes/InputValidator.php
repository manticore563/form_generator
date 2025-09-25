<?php
/**
 * InputValidator Class
 * Comprehensive input validation and sanitization for all field types
 */

class InputValidator {
    
    private static $instance = null;
    private $errors = [];
    
    // XSS prevention patterns
    private static $xssPatterns = [
        '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
        '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi',
        '/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/mi',
        '/<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>/mi',
        '/<applet\b[^<]*(?:(?!<\/applet>)<[^<]*)*<\/applet>/mi',
        '/<form\b[^<]*(?:(?!<\/form>)<[^<]*)*<\/form>/mi',
        '/javascript:/i',
        '/vbscript:/i',
        '/onload\s*=/i',
        '/onclick\s*=/i',
        '/onmouseover\s*=/i',
        '/onerror\s*=/i',
        '/onfocus\s*=/i',
        '/onblur\s*=/i',
        '/onchange\s*=/i',
        '/onsubmit\s*=/i'
    ];
    
    // SQL injection patterns
    private static $sqlPatterns = [
        '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|EXECUTE|UNION|SCRIPT)\b)/i',
        '/(\b(OR|AND)\s+\d+\s*=\s*\d+)/i',
        '/(\b(OR|AND)\s+[\'"]?\w+[\'"]?\s*=\s*[\'"]?\w+[\'"]?)/i',
        '/(\-\-|\#|\/\*|\*\/)/i',
        '/(;|\||&)/i'
    ];
    
    private function __construct() {}
    
    /**
     * Get singleton instance
     * @return InputValidator
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Validate and sanitize input based on field type
     * @param string $fieldType
     * @param mixed $value
     * @param array $options
     * @return array
     */
    public function validateField($fieldType, $value, $options = []) {
        $this->errors = [];
        
        // First, apply basic sanitization
        $sanitizedValue = $this->basicSanitize($value);
        
        // Check for XSS and SQL injection attempts
        if ($this->containsMaliciousContent($sanitizedValue)) {
            return [
                'valid' => false,
                'value' => null,
                'error' => 'Invalid input detected. Please remove any script tags or special characters.'
            ];
        }
        
        // Apply field-specific validation
        switch ($fieldType) {
            case 'text':
                return $this->validateText($sanitizedValue, $options);
            case 'email':
                return $this->validateEmail($sanitizedValue, $options);
            case 'number':
                return $this->validateNumber($sanitizedValue, $options);
            case 'aadhar':
                return $this->validateAadhar($sanitizedValue, $options);
            case 'phone':
                return $this->validatePhone($sanitizedValue, $options);
            case 'url':
                return $this->validateUrl($sanitizedValue, $options);
            case 'select':
            case 'radio':
                return $this->validateSelect($sanitizedValue, $options);
            case 'checkbox':
                return $this->validateCheckbox($sanitizedValue, $options);
            case 'textarea':
                return $this->validateTextarea($sanitizedValue, $options);
            case 'date':
                return $this->validateDate($sanitizedValue, $options);
            case 'time':
                return $this->validateTime($sanitizedValue, $options);
            default:
                return $this->validateGeneric($sanitizedValue, $options);
        }
    }
    
    /**
     * Basic sanitization for all inputs
     * @param mixed $value
     * @return mixed
     */
    private function basicSanitize($value) {
        if (is_array($value)) {
            return array_map([$this, 'basicSanitize'], $value);
        }
        
        if (!is_string($value)) {
            return $value;
        }
        
        // Remove null bytes
        $value = str_replace("\0", '', $value);
        
        // Trim whitespace
        $value = trim($value);
        
        // Convert special characters to HTML entities
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $value;
    }
    
    /**
     * Check for malicious content (XSS and SQL injection)
     * @param string $value
     * @return bool
     */
    private function containsMaliciousContent($value) {
        if (!is_string($value)) {
            return false;
        }
        
        // Check for XSS patterns
        foreach (self::$xssPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        // Check for SQL injection patterns
        foreach (self::$sqlPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate text field
     * @param string $value
     * @param array $options
     * @return array
     */
    private function validateText($value, $options) {
        if (empty($value)) {
            return ['valid' => true, 'value' => $value, 'error' => null];
        }
        
        // Check minimum length
        if (isset($options['minLength']) && strlen($value) < $options['minLength']) {
            return [
                'valid' => false,
                'value' => $value,
                'error' => "Minimum length is {$options['minLength']} characters."
            ];
        }
        
        // Check maximum length
        if (isset($options['maxLength']) && strlen($value) > $options['maxLength']) {
            return [
                'valid' => false,
                'value' => $value,
                'error' => "Maximum length is {$options['maxLength']} characters."
            ];
        }
        
        // Check pattern if provided
        if (isset($options['pattern']) && !preg_match($options['pattern'], $value)) {
            return [
                'valid' => false,
                'value' => $value,
                'error' => $options['patternError'] ?? 'Invalid format.'
            ];
        }
        
        return ['valid' => true, 'value' => $value, 'error' => null];
    }
    
    /**
     * Validate email field
     * @param string $value
     * @param array $options
     * @return array
     */
    private function validateEmail($value, $options) {
        if (empty($value)) {
            return ['valid' => true, 'value' => $value, 'error' => null];
        }
        
        // Basic email validation
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'value' => $value,
                'error' => 'Please enter a valid email address.'
            ];
        }
        
        // Additional email security checks
        $domain = substr(strrchr($value, "@"), 1);
        
        // Check for suspicious domains
        $suspiciousDomains = ['tempmail.org', '10minutemail.com', 'guerrillamail.com'];
        if (in_array(strtolower($domain), $suspiciousDomains)) {
            return [
                'valid' => false,
                'value' => $value,
                'error' => 'Temporary email addresses are not allowed.'
            ];
        }
        
        return ['valid' => true, 'value' => strtolower($value), 'error' => null];
    }
    
    /**
     * Validate number field
     * @param mixed $value
     * @param array $options
     * @return array
     */
    private function validateNumber($value, $options) {
        if (empty($value) && $value !== '0' && $value !== 0) {
            return ['valid' => true, 'value' => null, 'error' => null];
        }
        
        if (!is_numeric($value)) {
            return [
                'valid' => false,
                'value' => $value,
                'error' => 'Please enter a valid number.'
            ];
        }
        
        $numValue = floatval($value);
        
        // Check minimum value
        if (isset($options['min']) && $numValue < $options['min']) {
            return [
                'valid' => false,
                'value' => $value,
                'error' => "Value must be at least {$options['min']}."
            ];
        }
        
        // Check maximum value
        if (isset($options['max']) && $numValue > $options['max']) {
            return [
                'valid' => false,
                'value' => $value,
                'error' => "Value must be no more than {$options['max']}."
            ];
        }
        
        // Check if integer is required
        if (isset($options['integer']) && $options['integer'] && $numValue != intval($numValue)) {
            return [
                'valid' => false,
                'value' => $value,
                'error' => 'Please enter a whole number.'
            ];
        }
        
        return ['valid' => true, 'value' => $numValue, 'error' => null];
    }
    
    /**
     * Validate Aadhar number
     * @param string $value
     * @param array $options
     * @return array
     */
    private function validateAadhar($value, $options) {
        if (empty($value)) {
            return ['valid' => true, 'value' => $value, 'error' => null];
        }
        
        // Remove spaces and hyphens
        $cleanValue = preg_replace('/[\s\-]/', '', $value);
        
        // Check if it contains only digits
        if (!ctype_digit($cleanValue)) {
            return [
                'valid' => false,
                'value' => $value,
                'error' => 'Aadhar number must contain only digits.'
            ];
        }
        
        // Check if it's exactly 12 digits
        if (strlen($cleanValue) !== 12) {
            return [
                'valid' => false,
                'value' => $value,
                'error' => 'Aadhar number must be exactly 12 digits.'
            ];
        }
        
        // Format as XXXX XXXX XXXX
        $formatted = substr($cleanValue, 0, 4) . ' ' . substr($cleanValue, 4, 4) . ' ' . substr($cleanValue, 8, 4);
        
        return ['valid' => true, 'value' => $formatted, 'error' => null];
    }
    
    /**
     * Validate phone number
     * @param string $value
     * @param array $options
     * @return array
     */
    private function validatePhone($value, $options) {
        if (empty($value)) {
            return ['valid' => true, 'value' => $value, 'error' => null];
        }
        
        // Remove all non-digit characters
        $cleanValue = preg_replace('/\D/', '', $value);
        
        // Check length (assuming Indian phone numbers)
        if (strlen($cleanValue) < 10 || strlen($cleanValue) > 12) {
            return [
                'valid' => false,
                'value' => $value,
                'error' => 'Please enter a valid phone number.'
            ];
        }
        
        return ['valid' => true, 'value' => $cleanValue, 'error' => null];
    }
    
    /**
     * Validate URL
     * @param string $value
     * @param array $options
     * @return array
     */
    private function validateUrl($value, $options) {
        if (empty($value)) {
            return ['valid' => true, 'value' => $value, 'error' => null];
        }
        
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return [
                'valid' => false,
                'value' => $value,
                'error' => 'Please enter a valid URL.'
            ];
        }
        
        return ['valid' => true, 'value' => $value, 'error' => null];
    }
    
    /**
     * Validate select/radio field
     * @param string $value
     * @param array $options
     * @return array
     */
    private function validateSelect($value, $options) {
        if (empty($value)) {
            return ['valid' => true, 'value' => $value, 'error' => null];
        }
        
        if (!isset($options['allowedValues']) || !is_array($options['allowedValues'])) {
            return ['valid' => true, 'value' => $value, 'error' => null];
        }
        
        if (!in_array($value, $options['allowedValues'], true)) {
            return [
                'valid' => false,
                'value' => $value,
                'error' => 'Please select a valid option.'
            ];
        }
        
        return ['valid' => true, 'value' => $value, 'error' => null];
    }
    
    /**
     * Validate checkbox field
     * @param mixed $value
     * @param array $options
     * @return array
     */
    private function validateCheckbox($value, $options) {
        if (empty($value)) {
            return ['valid' => true, 'value' => [], 'error' => null];
        }
        
        if (!is_array($value)) {
            $value = [$value];
        }
        
        if (!isset($options['allowedValues']) || !is_array($options['allowedValues'])) {
            return ['valid' => true, 'value' => $value, 'error' => null];
        }
        
        $validValues = [];
        foreach ($value as $val) {
            if (in_array($val, $options['allowedValues'], true)) {
                $validValues[] = $val;
            }
        }
        
        if (empty($validValues) && !empty($value)) {
            return [
                'valid' => false,
                'value' => $value,
                'error' => 'Please select valid options.'
            ];
        }
        
        return ['valid' => true, 'value' => $validValues, 'error' => null];
    }
    
    /**
     * Validate textarea field
     * @param string $value
     * @param array $options
     * @return array
     */
    private function validateTextarea($value, $options) {
        // Use same validation as text field
        return $this->validateText($value, $options);
    }
    
    /**
     * Validate date field
     * @param string $value
     * @param array $options
     * @return array
     */
    private function validateDate($value, $options) {
        if (empty($value)) {
            return ['valid' => true, 'value' => $value, 'error' => null];
        }
        
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return [
                'valid' => false,
                'value' => $value,
                'error' => 'Please enter a valid date (YYYY-MM-DD).'
            ];
        }
        
        // Check minimum date
        if (isset($options['min'])) {
            $minDate = DateTime::createFromFormat('Y-m-d', $options['min']);
            if ($date < $minDate) {
                return [
                    'valid' => false,
                    'value' => $value,
                    'error' => "Date must be on or after {$options['min']}."
                ];
            }
        }
        
        // Check maximum date
        if (isset($options['max'])) {
            $maxDate = DateTime::createFromFormat('Y-m-d', $options['max']);
            if ($date > $maxDate) {
                return [
                    'valid' => false,
                    'value' => $value,
                    'error' => "Date must be on or before {$options['max']}."
                ];
            }
        }
        
        return ['valid' => true, 'value' => $value, 'error' => null];
    }
    
    /**
     * Validate time field
     * @param string $value
     * @param array $options
     * @return array
     */
    private function validateTime($value, $options) {
        if (empty($value)) {
            return ['valid' => true, 'value' => $value, 'error' => null];
        }
        
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value)) {
            return [
                'valid' => false,
                'value' => $value,
                'error' => 'Please enter a valid time (HH:MM).'
            ];
        }
        
        return ['valid' => true, 'value' => $value, 'error' => null];
    }
    
    /**
     * Generic validation for unknown field types
     * @param mixed $value
     * @param array $options
     * @return array
     */
    private function validateGeneric($value, $options) {
        return ['valid' => true, 'value' => $value, 'error' => null];
    }
    
    /**
     * Validate multiple fields at once
     * @param array $fields Array of field definitions
     * @param array $data Input data
     * @return array
     */
    public function validateFields($fields, $data) {
        $validatedData = [];
        $errors = [];
        
        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? $field['id'];
            $fieldType = $field['type'];
            $fieldValue = $data[$fieldName] ?? null;
            $fieldOptions = $field['validation'] ?? [];
            
            // Check if field is required
            if (isset($field['required']) && $field['required'] && $this->isEmpty($fieldValue)) {
                $errors[$fieldName] = ($field['label'] ?? $fieldName) . ' is required.';
                continue;
            }
            
            // Skip validation if field is empty and not required
            if ($this->isEmpty($fieldValue)) {
                $validatedData[$fieldName] = null;
                continue;
            }
            
            // Validate field
            $result = $this->validateField($fieldType, $fieldValue, $fieldOptions);
            
            if ($result['valid']) {
                $validatedData[$fieldName] = $result['value'];
            } else {
                $errors[$fieldName] = $result['error'];
            }
        }
        
        return [
            'valid' => empty($errors),
            'data' => $validatedData,
            'errors' => $errors
        ];
    }
    
    /**
     * Check if value is empty
     * @param mixed $value
     * @return bool
     */
    private function isEmpty($value) {
        if (is_array($value)) {
            return empty($value);
        }
        return $value === null || $value === '';
    }
}
?>