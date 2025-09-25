<?php
/**
 * Direct CSV Export Handler
 * Handles CSV export without database tracking
 */

// Initialize application
define('SEFP_INIT', true);
require_once '../config/config.php';
require_once 'SubmissionManager.php';
require_once 'FormManager.php';

// Check authentication (compatible with both login methods)
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    header('Location: ../auth/simple-login.php');
    exit;
}

// Get form ID and filters
$formId = $_POST['form_id'] ?? $_GET['form_id'] ?? '';
if (empty($formId)) {
    http_response_code(400);
    die('Form ID required');
}

$filters = [
    'search' => $_POST['search'] ?? $_GET['search'] ?? '',
    'date_from' => $_POST['date_from'] ?? $_GET['date_from'] ?? '',
    'date_to' => $_POST['date_to'] ?? $_GET['date_to'] ?? ''
];

try {
    $submissionManager = new SubmissionManager();
    $formManager = new FormManager();
    
    // Get form configuration
    $form = $formManager->getFormConfig($formId);
    if (!$form) {
        http_response_code(404);
        die('Form not found');
    }
    
    // Get all submissions for this form
    $allSubmissions = $submissionManager->getAllSubmissionsPublic($formId, $filters);
    
    // Generate CSV content directly
    $sanitizedTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', $form['title']);
    $filename = 'submissions_' . $sanitizedTitle . '_' . date('Y-m-d_H-i-s') . '.csv';
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('Pragma: no-cache');
    
    // Create CSV output stream
    $output = fopen('php://output', 'w');
    
    // Build CSV headers
    $headers = ['Submission ID', 'Submitted At', 'IP Address'];
    $fieldHeaders = [];
    $fileFieldHeaders = [];
    
    if (isset($form['config']['fields']) && is_array($form['config']['fields'])) {
        foreach ($form['config']['fields'] as $field) {
            $fieldHeaders[] = $field['label'] ?? $field['name'] ?? 'Unknown Field';
            
            // Add file URL columns for file fields
            if (in_array($field['type'] ?? '', ['file', 'photo', 'signature'])) {
                $fileFieldHeaders[] = ($field['label'] ?? $field['name'] ?? 'Unknown Field') . ' - Download URL';
            }
        }
    }
    
    $allHeaders = array_merge($headers, $fieldHeaders, $fileFieldHeaders);
    fputcsv($output, $allHeaders);
    
    // Write data rows if submissions exist
    if (!empty($allSubmissions)) {
        foreach ($allSubmissions as $submission) {
            $row = [
                $submission['id'],
                $submission['submitted_at'],
                $submission['ip_address'] ?? 'Unknown'
            ];
            
            // Add field data
            if (isset($form['config']['fields']) && is_array($form['config']['fields'])) {
                foreach ($form['config']['fields'] as $field) {
                    $fieldId = $field['id'] ?? $field['name'] ?? '';
                    $fieldName = $field['name'] ?? $field['id'] ?? '';
                    
                    // Try both field ID and name as keys
                    $value = $submission['submission_data'][$fieldId] ?? $submission['submission_data'][$fieldName] ?? '';
                    
                    // Handle array values (checkboxes, multi-select)
                    if (is_array($value)) {
                        $value = implode(', ', $value);
                    }
                    
                    $row[] = $value;
                }
            }
            
            // Add file information
            $submissionFiles = $submissionManager->getSubmissionFiles($submission['id']);
            $filesByField = [];
            foreach ($submissionFiles as $file) {
                $filesByField[$file['field_name']] = $file;
            }
            
            if (isset($form['config']['fields']) && is_array($form['config']['fields'])) {
                foreach ($form['config']['fields'] as $field) {
                    if (in_array($field['type'] ?? '', ['file', 'photo', 'signature'])) {
                        $fieldId = $field['id'] ?? $field['name'] ?? '';
                        $fieldName = $field['name'] ?? $field['id'] ?? '';
                        
                        if (isset($filesByField[$fieldId]) || isset($filesByField[$fieldName])) {
                            $fileData = $filesByField[$fieldId] ?? $filesByField[$fieldName];
                            // Generate simple download URL
                            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                            $host = $_SERVER['HTTP_HOST'];
                            $downloadUrl = $protocol . '://' . $host . '/uploads/serve.php?file=' . urlencode($fileData['id']);
                            $row[] = $downloadUrl;
                        } else {
                            $row[] = '';
                        }
                    }
                }
            }
            
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    error_log("Direct CSV export error: " . $e->getMessage());
    http_response_code(500);
    die('Export failed: ' . $e->getMessage());
}
?>