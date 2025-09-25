<?php
/**
 * Export Download Handler
 * Handles secure download of CSV export files
 */

// Initialize application
define('SEFP_INIT', true);
require_once '../config/config.php';
require_once 'SubmissionManager.php';

// Check authentication (compatible with both login methods)
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    header('Location: ../auth/simple-login.php');
    exit;
}

// Get export ID
$exportId = $_GET['id'] ?? '';
if (empty($exportId)) {
    http_response_code(400);
    die('Export ID required');
}

try {
    $submissionManager = new SubmissionManager();
    $export = $submissionManager->getExport($exportId);
    
    if (!$export) {
        http_response_code(404);
        die('Export not found or expired');
    }
    
    // Check if file exists
    if (!file_exists($export['filepath'])) {
        http_response_code(404);
        die('Export file not found on disk');
    }
    
    // Update download statistics
    $submissionManager->recordExportDownload($exportId);
    
    // Set headers for file download
    header('Content-Type: text/csv');
    header('Content-Length: ' . filesize($export['filepath']));
    header('Content-Disposition: attachment; filename="' . $export['filename'] . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('Pragma: no-cache');
    
    // Output file content
    readfile($export['filepath']);
    
} catch (Exception $e) {
    error_log("Export download error: " . $e->getMessage());
    http_response_code(500);
    die('Server error occurred');
}
?>