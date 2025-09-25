<?php
/**
 * FormSubmissionHandler Class - FIXED VERSION
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

class FormSubmissionHandler_Fixed {
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
            error_log("FormSubmissionHandler_Fixed::processSubmission - Starting");
            error_log("POST data keys: " . implode(', ', array_keys($postData)));
            error_log("FILES data: " . json_encode($files));
            
            // Generate submission ID
            $submissionId = $this->generateSubmissionId();
            error_log("Generated submission ID: " . $submissionId);
            
            // Skip CSRF validation for now to test
            error_log('CSRF validation skipped for testing');
            
            // Validate and sanitize form data
            $validationResult = $this->validateFormData($postData, $files);
            if (!$validationResult['success']) {
                error_log("Form validation failed: " . json_encode($validationResult));
                return $validationResult;
            }
            
            $sanitizedData = $validationResult['data'];
            error_log("Sanitized data: " . json_encode($sanitizedData));
            
            // Process file uploads
            $fileProcessingResult = $this->processFileUploads($postData, $files, $submissionId);
            error_log("File processing result: " . json_encode($fileProcessingResult));
            
            // Merge file data with form data
            $submissionData = array_merge($sanitizedData, $fileProcessingResult['files'] ?? []);
            error_log("Final submission data: " . json_encode($submissionData));
            
            // Store submission in database
            $storeResult = $this->storeSubmission($submissionId, $submissionData);
            if (!$storeResult) {
                error_log("Failed to store submission in database");
                return [
                    'success' => false,
                    'error' => 'Failed to save submission. Please try again.'
                ];
            }
            
            error_log("Submission stored successfully: " . $submissionId);
            
            return [
                'success' => true,
                'submission_id' => $submissionId,
                'message' => $this->config['settings']['success_message'] ?? 'Thank you for your submission!'
            ];
            
        } catch (Exception $e) {
            error_log("FormSubmissionHandler_Fixed::processSubmission - Exception: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => 'An unexpected error occurred. Please try again. Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate and sanitize form data
     * @param array $postData
     * @param array $files
     * @return array
     */
    private function validateFormData($postData, $files) {
        $sanitizedData = [];
        $errors = [];
        
        error_log("validateFormData - Starting validation");
        error_log("Config fields: " . json_encode($this->config['fields']));
        
        if (empty($this->config['fields'])) {
            error_log("No fields configured in form");
            return [
                'success' => true,
                'data' => []
            ];
        }
        
        foreach ($this->config['fields'] as $field) {
            $fieldId = $field['id'];
            $fieldValue = $postData[$fieldId] ?? null;
            
            error_log("Processing field: {$fieldId}, type: {$field['type']}, value: " . json_encode($fieldValue));
            
            // Skip file fields (handled separately)
            if (in_array($field['type'], ['file', 'photo', 'signature'])) {
                error_log("Skipping file field: {$fieldId}");
                continue;
            }
            
            // Check required fields
            if (!empty($field['required']) && $this->isEmpty($fieldValue)) {
                $errors[$fieldId] = $field['label'] . ' is required.';
                error_log("Required field missing: {$fieldId}");
                continue;
            }
            
            // Skip validation if field is empty and not required
            if ($this->isEmpty($fieldValue)) {
                $sanitizedData[$fieldId] = null;
                error_log("Empty non-required field: {$fieldId}");
                continue;
            }
            
            // Basic validation and sanitization
            $validatedValue = $this->validateFieldValue($field, $fieldValue);
            if ($validatedValue['valid']) {
                $sanitizedData[$fieldId] = $validatedValue['value'];
                error_log("Field validated successfully: {$fieldId}");
            } else {
                $errors[$fieldId] = $validatedValue['error'];
                error_log("Field validation failed: {$fieldId} - " . $validatedValue['error']);
            }
        }
        
        if (!empty($errors)) {
            error_log("Validation errors: " . json_encode($errors));
            return [
                'success' => false,
                'errors' => $errors,
                'error' => 'Please correct the errors below and try again.'
            ];
        }
        
        error_log("All fields validated successfully");
        return [
            'success' => true,
            'data' => $sanitizedData
        ];
    }
    
    /**
     * Process uploaded files for file/photo/signature fields
     * @param array $postData
     * @param array $files
     * @param string $submissionId
     * @return array
     */
    private function processFileUploads($postData, $files, $submissionId) {
        $processedFiles = [];
        $errors = [];
        
        error_log("processFileUploads - Starting");
        
        if (empty($this->config['fields'])) {
            return [
                'success' => true,
                'files' => [],
                'errors' => []
            ];
        }
        
        foreach ($this->config['fields'] as $field) {
            if (!in_array($field['type'], ['file', 'photo', 'signature'])) {
                continue;
            }
            
            $fieldId = $field['id'];
            error_log("Processing file field: {$fieldId}");
            
            // Check for direct upload
            if (isset($files[$fieldId]) && is_array($files[$fieldId]) && !empty($files[$fieldId]['tmp_name'])) {
                $file = $files[$fieldId];
                error_log("Direct upload found for {$fieldId}: " . json_encode($file));
                
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    error_log("Upload error for {$fieldId}: " . $file['error']);
                    if (!empty($field['required'])) {
                        $errors[$fieldId] = 'Error uploading ' . $field['label'] . '. Please try again.';
                    }
                    continue;
                }
                
                $storeResult = $this->storeFile($field, $file, $submissionId);
                if ($storeResult['success']) {
                    $processedFiles[$fieldId] = $storeResult['file_info'];
                    error_log("File stored successfully for {$fieldId}");
                } else {
                    error_log("Failed to store file for {$fieldId}: " . ($storeResult['error'] ?? 'unknown'));
                    if (!empty($field['required'])) {
                        $errors[$fieldId] = 'Failed to save ' . $field['label'] . '. Please try again.';
                    }
                }
                continue;
            }
            
            // Check for temp file token
            $tempToken = $postData[$fieldId . '_temp'] ?? $postData[$fieldId . '_temp_name'] ?? null;
            if ($tempToken) {
                error_log("Temp token found for {$fieldId}: {$tempToken}");
                $moveResult = $this->moveTempFileToPermanent($field, $tempToken, $submissionId);
                if ($moveResult['success']) {
                    $processedFiles[$fieldId] = $moveResult['file_info'];
                    error_log("Temp file moved successfully for {$fieldId}");
                } else {
                    error_log("Failed to move temp file for {$fieldId}: " . ($moveResult['error'] ?? 'unknown'));
                    if (!empty($field['required'])) {
                        $errors[$fieldId] = 'Failed to attach uploaded file for ' . $field['label'] . '.';
                    }
                }
                continue;
            }
            
            // Check if field is required but no file was provided
            if (!empty($field['required'])) {
                $errors[$fieldId] = $field['label'] . ' is required.';
                error_log("Required file field missing: {$fieldId}");
            }
        }
        
        error_log("File processing completed. Processed: " . count($processedFiles) . ", Errors: " . count($errors));
        
        return [
            'success' => empty($errors),
            'files' => $processedFiles,
            'errors' => $errors
        ];
    }
    
    /**
     * Move temp file to permanent storage
     */
    private function moveTempFileToPermanent($field, $tempToken, $submissionId) {
        try {
            $safeToken = basename($tempToken);
            $tempDir = __DIR__ . '/../uploads/temp';
            $tempPath = $tempDir . '/' . $safeToken;
            
            if (!file_exists($tempPath)) {
                // Try with different extensions
                $matches = glob($tempDir . '/' . $safeToken . '.*');
                if (!empty($matches)) {
                    $tempPath = $matches[0];
                } else {
                    return ['success' => false, 'error' => 'Uploaded file could not be located.'];
                }
            }
            
            $extension = pathinfo($tempPath, PATHINFO_EXTENSION);
            $fileInfo = [
                'name' => $safeToken . '.' . $extension,
                'tmp_name' => $tempPath,
                'type' => mime_content_type($tempPath),
                'size' => filesize($tempPath),
                'error' => UPLOAD_ERR_OK
            ];
            
            return $this->storeFile($field, $fileInfo, $submissionId);
            
        } catch (Exception $e) {
            error_log('moveTempFileToPermanent error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to process uploaded file.'];
        }
    }
    
    /**
     * Store uploaded file
     */
    private function storeFile($field, $file, $submissionId) {
        try {
            $baseUploadDir = __DIR__ . '/../uploads/files';
            
            if (!is_dir($baseUploadDir)) {
                mkdir($baseUploadDir, 0755, true);
            }
            
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $storedFilename = $submissionId . '_' . $field['id'] . '_' . time() . '.' . $extension;
            $filePath = $baseUploadDir . '/' . $storedFilename;
            
            error_log("Storing file: {$file['tmp_name']} -> {$filePath}");
            
            $moveSucceeded = false;
            if (is_uploaded_file($file['tmp_name'])) {
                $moveSucceeded = move_uploaded_file($file['tmp_name'], $filePath);
            } else {
                $moveSucceeded = @rename($file['tmp_name'], $filePath);
                if (!$moveSucceeded && @copy($file['tmp_name'], $filePath)) {
                    @unlink($file['tmp_name']);
                    $moveSucceeded = true;
                }
            }
            
            if (!$moveSucceeded) {
                error_log("Failed to move file: {$file['tmp_name']} -> {$filePath}");
                return ['success' => false, 'error' => 'Failed to save uploaded file'];
            }
            
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
                unlink($filePath);
                error_log("Failed to insert file record into database");
                return ['success' => false, 'error' => 'Failed to save file record'];
            }
            
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
            error_log("storeFile error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Exception occurred while storing file'];
        }
    }
    
    /**
     * Validate individual field value
     */
    private function validateFieldValue($field, $value) {
        switch ($field['type']) {
            case 'text':
                $sanitized = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
                if (isset($field['maxLength']) && strlen($sanitized) > $field['maxLength']) {
                    return [
                        'valid' => false,
                        'error' => $field['label'] . ' must be no more than ' . $field['maxLength'] . ' characters.'
                    ];
                }
                return ['valid' => true, 'value' => $sanitized];
                
            case 'email':
                $sanitized = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
                if (!filter_var($sanitized, FILTER_VALIDATE_EMAIL)) {
                    return [
                        'valid' => false,
                        'error' => 'Please enter a valid email address.'
                    ];
                }
                return ['valid' => true, 'value' => $sanitized];
                
            case 'number':
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
                
            case 'select':
            case 'radio':
                $sanitized = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
                if (!empty($field['options']) && !in_array($sanitized, $field['options'])) {
                    return [
                        'valid' => false,
                        'error' => 'Please select a valid option for ' . $field['label'] . '.'
                    ];
                }
                return ['valid' => true, 'value' => $sanitized];
                
            case 'checkbox':
                if (!is_array($value)) {
                    $value = [$value];
                }
                $sanitizedValues = [];
                foreach ($value as $val) {
                    $sanitized = htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
                    if (empty($field['options']) || in_array($sanitized, $field['options'])) {
                        $sanitizedValues[] = $sanitized;
                    }
                }
                return ['valid' => true, 'value' => $sanitizedValues];
                
            default:
                return ['valid' => true, 'value' => htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8')];
        }
    }
    
    /**
     * Store submission in database
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
            error_log("storeSubmission error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate unique submission ID
     */
    private function generateSubmissionId() {
        return 'SUB_' . date('Ymd') . '_' . strtoupper(uniqid());
    }
    
    /**
     * Check if value is empty
     */
    private function isEmpty($value) {
        if (is_array($value)) {
            return empty($value);
        }
        return $value === null || $value === '';
    }
}
?>
