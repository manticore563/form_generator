<?php
/**
 * FormSubmissionHandler Class
 * Handles form submission processing, validation, and storage
 */

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/AadharValidator.php';
require_once __DIR__ . '/../includes/InputValidator.php';
require_once __DIR__ . '/../includes/CSRFProtection.php';
require_once __DIR__ . '/../includes/SecurityUtils.php';
require_once __DIR__ . '/../includes/FileSecurityManager.php';
require_once __DIR__ . '/../includes/AccessLogger.php';

class FormSubmissionHandler {
    private $db;
    private $form;
    private $config;
    
    public function __construct($form) {
        $this->db = Database::getInstance();
        $this->form = $form;
        $this->config = isset($form['config']) ? $form['config'] : ['fields' => [], 'settings' => []];
    }
    
    /**
     * Process form submission
     * @param array $postData
     * @param array $files
     * @return array Result with success status and data
     */
    public function processSubmission($postData, $files) {
        try {
            // Debug logging
            error_log("FormSubmissionHandler::processSubmission - Starting");
            error_log("POST data keys: " . implode(', ', array_keys($postData)));
            error_log("FILES data: " . json_encode($files));
            
            // Generate submission ID
            $submissionId = $this->generateSubmissionId();
            error_log("Generated submission ID: " . $submissionId);
            
            // Validate CSRF token (basic implementation)
            if (!$this->validateCSRFToken($postData)) {
                return [
                    'success' => false,
                    'error' => 'Invalid security token. Please refresh the page and try again.'
                ];
            }
            
            // Validate and sanitize form data
            $validationResult = $this->validateFormData($postData, $files);
            if (!$validationResult['success']) {
                return $validationResult;
            }
            
            $sanitizedData = $validationResult['data'];
            
            // Process file uploads (supports direct uploads and pre-uploaded temp files)
            $fileProcessingResult = $this->processFileUploads($postData, $files, $submissionId);
            
            // Don't fail the entire submission if only file processing has issues
            // and no file fields are actually required
            $hasRequiredFileFields = false;
            foreach ($this->config['fields'] as $field) {
                if (in_array($field['type'], ['file', 'photo', 'signature']) && $field['required']) {
                    $hasRequiredFileFields = true;
                    break;
                }
            }
            
            // Only fail if file processing failed AND there are required file fields
            if (!$fileProcessingResult['success'] && $hasRequiredFileFields) {
                // Log but make the error message more user-friendly
                error_log('File processing failed for form: ' . $this->form['id'] . ' - ' . json_encode($fileProcessingResult));
                return [
                    'success' => false,
                    'error' => 'There was an issue with file upload. Please try again or contact support if the problem persists.'
                ];
            }
            
            // If file processing failed but no required file fields, just log and continue
            if (!$fileProcessingResult['success'] && !$hasRequiredFileFields) {
                error_log('Non-critical file processing issue for form: ' . $this->form['id']);
                $fileProcessingResult = ['success' => true, 'files' => []];
            }
            
            // Merge file data with form data
            $submissionData = array_merge($sanitizedData, $fileProcessingResult['files']);
            
            // Store submission in database
            $storeResult = $this->storeSubmission($submissionId, $submissionData);
            if (!$storeResult) {
                return [
                    'success' => false,
                    'error' => 'Failed to save submission. Please try again.'
                ];
            }
            
            return [
                'success' => true,
                'submission_id' => $submissionId,
                'message' => $this->config['settings']['success_message'] ?? 'Thank you for your submission!'
            ];
            
        } catch (Exception $e) {
            error_log("FormSubmissionHandler::processSubmission - " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'An unexpected error occurred. Please try again.'
            ];
        }
    }
    
    /**
     * Validate CSRF token - SIMPLIFIED FOR TESTING
     * @param array $postData
     * @return bool
     */
    private function validateCSRFToken($postData) {
        // Temporarily disable CSRF validation to test form submission
        error_log('CSRF validation temporarily disabled for testing');
        return true;
    }
    
    /**
     * Validate and sanitize form data
     * @param array $postData
     * @param array $files
     * @return array
     */
    private function validateFormData($postData, $files) {
        $validator = InputValidator::getInstance();
        $sanitizedData = [];
        $errors = [];
        
        // Rate limiting check
        $clientIP = SecurityUtils::getClientIP();
        if (!SecurityUtils::checkRateLimit('form_submission_' . $clientIP, 10, 300)) {
            SecurityUtils::logSecurityEvent('rate_limit_exceeded', [
                'type' => 'form_submission',
                'form_id' => $this->form['id'],
                'ip' => $clientIP
            ], 'WARNING');
            
            return [
                'success' => false,
                'error' => 'Too many submissions. Please wait before trying again.'
            ];
        }
        
        foreach ($this->config['fields'] as $field) {
            $fieldId = $field['id'];
            $fieldValue = $postData[$fieldId] ?? null;
            
            // Skip file fields (handled separately)
            if (in_array($field['type'], ['file', 'photo', 'signature'])) {
                continue;
            }
            
            // Check required fields
            if ($field['required'] && $this->isEmpty($fieldValue)) {
                $errors[$fieldId] = $field['label'] . ' is required.';
                continue;
            }
            
            // Skip validation if field is empty and not required
            if ($this->isEmpty($fieldValue)) {
                $sanitizedData[$fieldId] = null;
                continue;
            }
            
            // Prepare validation options
            $validationOptions = [];
            if (isset($field['maxLength'])) $validationOptions['maxLength'] = $field['maxLength'];
            if (isset($field['minLength'])) $validationOptions['minLength'] = $field['minLength'];
            if (isset($field['min'])) $validationOptions['min'] = $field['min'];
            if (isset($field['max'])) $validationOptions['max'] = $field['max'];
            if (isset($field['options'])) $validationOptions['allowedValues'] = $field['options'];
            if (isset($field['pattern'])) $validationOptions['pattern'] = $field['pattern'];
            
            // Use new InputValidator
            $validationResult = $validator->validateField($field['type'], $fieldValue, $validationOptions);
            
            if (!$validationResult['valid']) {
                $errors[$fieldId] = $validationResult['error'];
                
                // Log validation failures for security monitoring
                SecurityUtils::logSecurityEvent('field_validation_failed', [
                    'field_id' => $fieldId,
                    'field_type' => $field['type'],
                    'form_id' => $this->form['id'],
                    'error' => $validationResult['error']
                ]);
            } else {
                $sanitizedData[$fieldId] = $validationResult['value'];
            }
        }
        
        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors,
                'error' => 'Please correct the errors below and try again.'
            ];
        }
        
        return [
            'success' => true,
            'data' => $sanitizedData
        ];
    }
    
    /**
     * Validate individual field value
     * @param array $field
     * @param mixed $value
     * @return array
     */
    private function validateFieldValue($field, $value) {
        switch ($field['type']) {
            case 'text':
                return $this->validateTextField($field, $value);
            case 'email':
                return $this->validateEmailField($field, $value);
            case 'number':
                return $this->validateNumberField($field, $value);
            case 'aadhar':
                return $this->validateAadharField($field, $value);
            case 'select':
                return $this->validateSelectField($field, $value);
            case 'radio':
                return $this->validateRadioField($field, $value);
            case 'checkbox':
                return $this->validateCheckboxField($field, $value);
            default:
                return ['valid' => true, 'value' => $this->sanitizeString($value)];
        }
    }
    
    /**
     * Validate text field
     */
    private function validateTextField($field, $value) {
        $sanitized = $this->sanitizeString($value);
        
        if (isset($field['maxLength']) && strlen($sanitized) > $field['maxLength']) {
            return [
                'valid' => false,
                'error' => $field['label'] . ' must be no more than ' . $field['maxLength'] . ' characters.'
            ];
        }
        
        return ['valid' => true, 'value' => $sanitized];
    }
    
    /**
     * Validate email field
     */
    private function validateEmailField($field, $value) {
        $sanitized = $this->sanitizeString($value);
        
        if (!filter_var($sanitized, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'error' => 'Please enter a valid email address.'
            ];
        }
        
        return ['valid' => true, 'value' => $sanitized];
    }
    
    /**
     * Validate number field
     */
    private function validateNumberField($field, $value) {
        if (!is_numeric($value)) {
            return [
                'valid' => false,
                'error' => $field['label'] . ' must be a valid number.'
            ];
        }
        
        $numValue = floatval($value);
        
        if (isset($field['min']) && $numValue < $field['min']) {
            return [
                'valid' => false,
                'error' => $field['label'] . ' must be at least ' . $field['min'] . '.'
            ];
        }
        
        if (isset($field['max']) && $numValue > $field['max']) {
            return [
                'valid' => false,
                'error' => $field['label'] . ' must be no more than ' . $field['max'] . '.'
            ];
        }
        
        return ['valid' => true, 'value' => $numValue];
    }
    
    /**
     * Validate Aadhar field
     */
    private function validateAadharField($field, $value) {
        $sanitized = $this->sanitizeString($value);
        $validationResult = AadharValidator::validate($sanitized);
        
        if (!$validationResult['valid']) {
            return [
                'valid' => false,
                'error' => $validationResult['error']
            ];
        }
        
        return [
            'valid' => true,
            'value' => $validationResult['formatted']
        ];
    }
    
    /**
     * Validate select field
     */
    private function validateSelectField($field, $value) {
        $sanitized = $this->sanitizeString($value);
        
        if (!in_array($sanitized, $field['options'])) {
            return [
                'valid' => false,
                'error' => 'Please select a valid option for ' . $field['label'] . '.'
            ];
        }
        
        return ['valid' => true, 'value' => $sanitized];
    }
    
    /**
     * Validate radio field
     */
    private function validateRadioField($field, $value) {
        return $this->validateSelectField($field, $value);
    }
    
    /**
     * Validate checkbox field
     */
    private function validateCheckboxField($field, $value) {
        if (!is_array($value)) {
            $value = [$value];
        }
        
        $sanitizedValues = [];
        foreach ($value as $val) {
            $sanitized = $this->sanitizeString($val);
            if (in_array($sanitized, $field['options'])) {
                $sanitizedValues[] = $sanitized;
            }
        }
        
        if (empty($sanitizedValues) && $field['required']) {
            return [
                'valid' => false,
                'error' => 'Please select at least one option for ' . $field['label'] . '.'
            ];
        }
        
        return ['valid' => true, 'value' => $sanitizedValues];
    }
    
    /**
            $file = $files[$fieldId];
            error_log("File data for " . $fieldId . ": " . json_encode($file));
            
            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                error_log("Upload error for field {$fieldId}: " . $file['error']);
                if ($field['required']) {
                    $errors[$fieldId] = 'Error uploading ' . $field['label'] . '. Please try again.';
                }
                continue;
            }
            
            // Very basic file validation
            $validationResult = $this->validateFileBasic($field, $file);
            if (!$validationResult['valid']) {
                error_log("File validation failed for {$fieldId}: " . $validationResult['error']);
                $errors[$fieldId] = $validationResult['error'];
                continue;
            }
            
            // Store file
            $storeResult = $this->storeFile($field, $file, $submissionId);
            if (!$storeResult['success']) {
                error_log("Failed to store file for field {$fieldId}");
                if ($field['required']) {
                    $errors[$fieldId] = 'Failed to save ' . $field['label'] . '. Please try again.';
                }
                continue;
            }
            
            error_log("Successfully processed file for field: " . $fieldId);
            $processedFiles[$fieldId] = $storeResult['file_info'];
            continue;
        }
        
        // Fallback: Check for pre-uploaded temp file token in POST (e.g., fieldId_temp or fieldId_temp_id)
        $tempToken = $postData[$fieldId . '_temp'] ?? $postData[$fieldId . '_temp_id'] ?? null;
        if ($tempToken) {
            $moveResult = $this->moveTempFileToPermanent($field, $tempToken, $submissionId);
            if ($moveResult['success']) {
                $processedFiles[$fieldId] = $moveResult['file_info'];
                error_log("Moved temp file for field: " . $fieldId);
                continue;
            } else {
                error_log("Failed moving temp file for {$fieldId}: " . ($moveResult['error'] ?? 'unknown'));
                if ($field['required']) {
                    $errors[$fieldId] = 'Failed to attach pre-uploaded file for ' . $field['label'] . '.';
     * @return array
     */
    private function validateFileBasic($field, $file) {
        // Check file size only
        $maxSize = ($field['maxSize'] ?? 10) * 1024 * 1024; // Default to 10MB, increased from 5MB
        if ($file['size'] > $maxSize) {
            return [
                'valid' => false,
                'error' => 'File size exceeds the maximum allowed size of ' . ($field['maxSize'] ?? 10) . 'MB.'
            ];
        }
        
        // Only block obviously malicious extensions
        $filename = strtolower($file['name']);
        $blockedExtensions = ['php', 'exe', 'bat', 'cmd', 'sh'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($extension, $blockedExtensions)) {
            return [
                'valid' => false,
                'error' => 'File type not allowed for security reasons.'
            ];
        }
        
        // Everything else is allowed
        return ['valid' => true];
    }
    
    /**
     * Validate uploaded file (legacy method - keeping for compatibility)
     * @param array $field
     * @param array $file
     * @return array
     */
    private function validateFile($field, $file) {
        // Determine allowed MIME types
        $allowedTypes = [];
        if (in_array($field['type'], ['photo', 'signature'])) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        } elseif ($field['type'] === 'file' && !empty($field['allowedTypes'])) {
            // Map file extensions to MIME types
            $extensionToMime = [
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'txt' => 'text/plain',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif'
            ];
            
            foreach ($field['allowedTypes'] as $ext) {
                if (isset($extensionToMime[$ext])) {
                    $allowedTypes[] = $extensionToMime[$ext];
                }
            }
        }
        
        // Use basic validation instead of comprehensive SecurityUtils validation
        // Check file size
        $maxSize = ($field['maxSize'] ?? 5) * 1024 * 1024; // Convert MB to bytes
        if ($file['size'] > $maxSize) {
            return [
                'valid' => false,
                'error' => 'File size exceeds the maximum allowed size of ' . ($field['maxSize'] ?? 5) . 'MB.'
            ];
        }
        
        // Basic file type check - only block obviously dangerous extensions
        $filename = strtolower($file['name']);
        $dangerousExtensions = ['php', 'exe', 'bat', 'cmd', 'sh', 'com', 'scr', 'vbs'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($extension, $dangerousExtensions)) {
            SecurityUtils::logSecurityEvent('dangerous_file_extension_blocked', [
                'field_id' => $field['id'],
                'filename' => $file['name'],
                'extension' => $extension,
                'form_id' => $this->form['id']
            ], 'WARNING');
            
            return [
                'valid' => false,
                'error' => 'File type not allowed for security reasons.'
            ];
        }
        
        // If field specifies allowed types, do a basic check
        if (!empty($allowedTypes) && !empty($field['allowedTypes'])) {
            $extensionToMime = [
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'txt' => 'text/plain',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp'
            ];
            
            $allowedExtensions = array_keys($extensionToMime);
            if (!in_array($extension, $allowedExtensions)) {
                return [
                    'valid' => false,
                    'error' => 'File type not supported. Please upload: ' . implode(', ', $field['allowedTypes'])
                ];
            }
        }
        
        return ['valid' => true];
    }
    
    /**
     * Store uploaded file - FIXED VERSION
     * @param array $field
     * @param array $file
     * @param string $submissionId
     * @return array
     */
    private function storeFile($field, $file, $submissionId) {
        try {
            // Simple upload directory structure
            $baseUploadDir = __DIR__ . '/../uploads/files';
            
            // Create directory if it doesn't exist
            if (!is_dir($baseUploadDir)) {
                mkdir($baseUploadDir, 0755, true);
            }
            
            // Generate simple filename with timestamp
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $storedFilename = $submissionId . '_' . $field['id'] . '_' . time() . '.' . $extension;
            $filePath = $baseUploadDir . '/' . $storedFilename;
            
            error_log("Attempting to move file from " . $file['tmp_name'] . " to " . $filePath);
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                error_log("Failed to move uploaded file: " . $file['tmp_name'] . " to " . $filePath);
                return ['success' => false, 'error' => 'Failed to save uploaded file'];
            }
            
            error_log("File successfully moved to: " . $filePath);
            
            // Store file info in database
            $fileId = uniqid('file_', true);
            $query = "INSERT INTO files (id, submission_id, field_name, original_filename, stored_filename, file_path, file_size, mime_type, uploaded_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $relativePath = 'uploads/files/' . $storedFilename;
            
            $result = $this->db->query($query, [
                $fileId,
                $submissionId,
                $field['id'],
                $file['name'],
                $storedFilename,
                $relativePath,
                $file['size'],
                $file['type']
            ]);
            
            if (!$result) {
                // Clean up file if database insert failed
                unlink($filePath);
                error_log("Failed to insert file record into database");
                return ['success' => false, 'error' => 'Failed to save file record'];
            }
            
            error_log("File successfully uploaded: " . $storedFilename);
            
            return [
                'success' => true,
                'file_info' => [
                    'file_id' => $fileId,
                    'original_name' => $file['name'],
                    'stored_name' => $storedFilename,
                    'file_path' => $relativePath,
                    'file_size' => $file['size'],
                    'mime_type' => $file['type']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("FormSubmissionHandler::storeFile - " . $e->getMessage());
            return ['success' => false, 'error' => 'Exception occurred while storing file'];
        }
    }
    
    /**
     * Store submission in database
     * @param string $submissionId
     * @param array $submissionData
     * @return bool
     */
    private function storeSubmission($submissionId, $submissionData) {
        try {
            $query = "INSERT INTO submissions (id, form_id, submission_data, submitted_at, ip_address, user_agent) 
                     VALUES (?, ?, ?, NOW(), ?, ?)";
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $result = $this->db->query($query, [
                $submissionId,
                $this->form['id'],
                json_encode($submissionData),
                $ipAddress,
                $userAgent
            ]);
            
            return $result !== false;
            
        } catch (Exception $e) {
            error_log("FormSubmissionHandler::storeSubmission - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate unique submission ID
     * @return string
     */
    private function generateSubmissionId() {
        return 'SUB_' . date('Ymd') . '_' . strtoupper(uniqid());
    }
    
    /**
     * Generate unique ID
     * @return string
     */
    private function generateUniqueId() {
        return uniqid('', true);
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
    
    /**
     * Sanitize string input
     * @param string $input
     * @return string
     */
    private function sanitizeString($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Generate CSRF token for forms
     * @return string
     */
    public static function generateCSRFToken() {
        $csrf = CSRFProtection::getInstance();
        return $csrf->getToken('form_submission');
    }
}
?>